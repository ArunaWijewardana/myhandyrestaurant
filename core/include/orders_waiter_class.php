<?php
/**
* My Handy Restaurant
*
* http://www.myhandyrestaurant.org
*
* My Handy Restaurant is a restaurant complete management tool.
* Visit {@link http://www.myhandyrestaurant.org} for more info.
* Copyright (C) 2003-2004 Fabio De Pascale
* 
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*
* @author		Fabio 'Kilyerd' De Pascale <public@fabiolinux.com>
* @package		MyHandyRestaurant
* @copyright		Copyright 2003-2005, Fabio De Pascale
*/

class order {
	var $id;
	var $table;
	var $data;
	var $output;
	var $ingredients;
	
	function order($input=0) {
		$this->table='#prefix#orders';

		if(is_int($input)) {
			$this->id=$input;
			if(!$this->exists()) {
				$this->id=0;
				return 1;
			}
			$this->get ();
			
			$this -> price ();
		}
		
		return 0;
	}
	
	function exists() {
		$query="SELECT `id` FROM `".$this->table."` WHERE id='".$this->id."'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return 0;
		
		return mysql_num_rows($res);
	}
	
	function prepare_default_array ($dishid) {
		//$quantity=get_conf(__FILE__,__LINE__,"default_quantity");
		$quantity=0;
		
		$query ="SELECT * FROM `#prefix#dishes` WHERE `id` = '$dishid'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;
		
		$arr = mysql_fetch_array ($res);
		
		$price = $arr['price']*$quantity;
		$destid=$arr['destid'];
		if ($dishid==SERVICE_ID) {
			$printed='0000-00-00 00:00:00';
		} else $printed=NULL;

		$new_arr=array(
			'dishid' => $dishid,
			'sourceid' => $_SESSION['sourceid'],
			'quantity' => $quantity,
			'price' => $price,
			'printed' => $printed,
			'dest_id' => $destid
		);

		if ($arr['generic']) $new_arr['extra_care']=1;
		
		$this->data=$new_arr;
		return 0;
	}
	
	function getOverridePrice ()
	{
		return $this->data['override_price'];
	
	}
	
	function copy ($dest=0) {
		if (!$dest || !$dest -> exists ()) {
			$dest = new order();
			$create = true;
		}
		
		$this->get();
		
		$dest -> data = $this -> data;
		
		unset($dest -> data['id']);
		unset($dest -> data['associated_id']);
		unset($dest -> data['price']);
		unset($dest -> data['timestamp']);
		
		if ($create) $id = $dest -> create ();
		else $dest -> set ();
		
		return $dest -> id;
	}
	
	function delete () {
		if ($this->id == $this -> data['associated_id'])
			$query="DELETE FROM `#prefix#orders` WHERE `associated_id`='".$this->id."'";
		else $query="DELETE FROM `#prefix#orders` WHERE `id`='".$this->id."' LIMIT 1";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return mysql_errno();

		$this->id=0;
		unset ($this);
		return 0;
	}
	
	function create () {
		if(!is_array($this->data)) return -1;

		// Now we'll build the correct INSERT query, based on the fields provided
		$query="INSERT INTO `#prefix#orders` (";
		for (reset ($this->data); list ($key, $value) = each ($this->data); ) {
			$query.="`".$key."`,";
		}
		// strips the last comma that has been put
		$query = substr ($query, 0, strlen($query)-1);
		$query.=") VALUES (";
		for (reset ($this->data); list ($key, $value) = each ($this->data); ) {
			if($value==NULL && $key=='printed') $query.="NULL,";
			else $query.="'".$value."',";
		}
		// strips the last comma that has been put
		$query = substr ($query, 0, strlen($query)-1);	$query.=")";

		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;

		$this->id=mysql_insert_id();
		$this->get ();
		return 0;
	}
	
	function get () {
		$query="SELECT * FROM `#prefix#orders` WHERE `id`='".$this->id."'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return mysql_errno();
		
		$arr=mysql_fetch_array($res,MYSQL_ASSOC);
		
		$this->data=$arr;

		return 0;
	}
	
	function set () {
		if(!is_array($this->data)) return -1;

		// Now we'll build the correct UPDATE query, based on the fields provided
		$query="UPDATE `#prefix#orders` SET ";
		for (reset ($this->data); list ($key, $value) = each ($this->data); ) {
			if($value==NULL && $key=='printed') $query.="`".$key."`= NULL,";
			else $query.="`".$key."`='".$value."',";
		}
		// strips the last comma that has been put
		$query = substr ($query, 0, strlen($query)-1);
		$query.=" WHERE `id`='".$this->id."'";

		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;

		$this->get ();

		if($this -> sync_suborders()) return ERR_SYNCING_SUBORDERS;

		$this -> price ();

		return 0;
	}
	
	function sync_suborders () {
		$ass_id = (int) $this->data['associated_id'];
	
		if(!$main = new order ($ass_id)) return ERR_COULD_NOT_CREATE_ORDER_OBJECT;
		
		$sync_array = array ('suspend','printed','extra_care','sourceid','quantity','priority','paid','deleted','dest_id');
		
		$query="UPDATE `#prefix#orders` SET ";
		foreach ( $sync_array as $value) {
			if($main->data[$value]==NULL && $value=='printed') $query.="`".$value."`=NULL,";
			else $query.="`".$value."`='".$main->data[$value]."',";
		}
		// strips the last comma that has been put
		$query = substr ($query, 0, strlen($query)-1);
		$query.=" WHERE `associated_id`='".$main->data['id']."'";

		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;
		
		return 0;
	}
	
	function ingredients_arrays () {
		// only works with normal dishes, not with mods
		if ($this->data['dishid']==MOD_ID) return 0;
		
		$ingreds=array();
		$dispingreds=array();
		
		$query ="SELECT * FROM `#prefix#dishes`
		WHERE `id` = '".$this->data['dishid']."'
		LIMIT 1";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return mysql_errno();
		
		$arr = mysql_fetch_array ($res);
		
		if(!empty($arr['ingreds'])) $ingreds = explode(' ',$arr['ingreds']);
		$this->ingredients['nominal']=$ingreds;
		if(!empty($arr['dispingreds'])) $dispingreds = explode(' ',$arr['dispingreds']);

		foreach ($ingreds as $key => $value) {
			$tmp = new ingredient ($value);
			if($tmp->data['deleted']) {
				unset($ingreds[$key]);
				continue;
			}
			$name=$tmp -> name($_SESSION['language']);
			$ingreds [ucfirst($name)] = $value;
			unset ($ingreds[$key]);
		}
		foreach ($dispingreds as $key => $value) {
			$tmp = new ingredient ($value);
			if($tmp->data['deleted']) {
				unset($dispingreds[$key]);
				continue;
			}
			$name=$tmp -> name($_SESSION['language']);
			$dispingreds [ucfirst($name)] = $value;
			unset ($dispingreds[$key]);
		}

		if (empty($dispingreds)) {
			$query ="SELECT #prefix#ingreds.id, #prefix#ingreds#lang#.table_name";
			$query .= " FROM `#prefix#ingreds`";
			$query .= " JOIN `#prefix#ingreds#lang#`";
			$query .= " WHERE (#prefix#ingreds.category = '".$this->data['category']."' OR #prefix#ingreds.category = '0')";
			$query .= " AND #prefix#ingreds#lang#.table_id = #prefix#ingreds.id";
			$query .= " AND #prefix#ingreds.deleted = '0'";
			$res=common_query($query,__FILE__,__LINE__);
			if(!$res) return mysql_errno();
			
			while ($arr = mysql_fetch_array($res)) {
				$dispingreds[ucfirst($arr['table_name'])] = $arr ['id'];
			}
		}
		
		$available_remove = $ingreds;
		$available_add = $dispingreds;
		
		// deletes all the found ingredients from the available ingreds list
		foreach ($ingreds as $value) {
			if ($keydel=array_search($value,$available_add)) unset($available_add[$keydel]);
		}
		
		$query ="SELECT #prefix#orders.*, #prefix#ingreds#lang#.table_name";
		$query .= " FROM `#prefix#orders`";
		$query .= " JOIN `#prefix#ingreds#lang#`";
		$query .= " WHERE #prefix#orders.associated_id = '".$this->id."'";
		$query .= " AND #prefix#orders.id != '".$this->id."'";
		$query .= " AND #prefix#ingreds#lang#.table_id = #prefix#orders.ingredid";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;
		
		while($arr = mysql_fetch_array ($res)) {
			$localid = (int) $arr['id'];
			if(!$local = new order ($localid)) return 1;
			
			if ($arr['dishid']==MOD_ID) {
				switch ($arr['operation']) {
					case '-1':
						$keyfound=array_search($arr['ingredid'],$available_add);
						if ($keyfound===FALSE) {
							$available_add[ucfirst($arr['table_name'])]=$arr['ingredid'];
						}
						$keyfound=array_search($arr['ingredid'],$available_remove);
						if ($keyfound!==FALSE) {
							unset($available_remove[$keyfound]);
						}
						break;
					case '1':
						$keyfound=array_search($arr['ingredid'],$available_add);
						if ($keyfound!==FALSE) {
							unset($available_add[$keyfound]);
						}
						$keyfound=array_search($arr['ingredid'],$available_remove);
						if ($keyfound===FALSE) {
							$available_remove[ucfirst($arr['table_name'])]=$arr['ingredid'];
						}
						break;
				}
			}
		}

		ksort($available_add);
		$this->ingredients['available']=$available_add;
		ksort($available_remove);
		$this->ingredients['contained']=$available_remove;

		return 0;
	}
	
	function price_mods_normal ($only_notfree=false) {
		// $query ="SELECT * FROM `#prefix#orders` WHERE `associated_id` = '".$this->id."' AND `id` != '".$this->id."' AND `deleted`='0'";
		
		$query ="SELECT #prefix#orders.* FROM `#prefix#orders` JOIN `#prefix#ingreds`
		WHERE #prefix#orders.ingredid = #prefix#ingreds.id
		AND #prefix#orders.associated_id = '".$this->id."'
		AND #prefix#orders.id != '".$this->id."'
		AND #prefix#ingreds.deleted = '0'
		AND #prefix#orders.deleted='0'";
		if ($only_notfree) $query .= "AND #prefix#ingreds.price != '0'";

		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;

		while ($arr = mysql_fetch_array ($res)) {
			$ingredid = $arr['ingredid'];
			$ing = new ingredient ($ingredid);

			$ord_local_id = (int) $arr ['id'];

			$price = $ing -> get ('price') * $arr['quantity'] * $arr['operation'];
			
			$query ="UPDATE `#prefix#orders` SET `price`='".$price."' WHERE `id` = '".$ord_local_id."'";
			$res2=common_query($query,__FILE__,__LINE__);
			if(!$res2) return ERR_MYSQL;
		}
		return 0;
	}

	function price_mods_autocalc () {
		$query = "SELECT #prefix#orders.* FROM `#prefix#orders` JOIN `#prefix#ingreds`
		WHERE #prefix#orders.ingredid = #prefix#ingreds.id
		AND #prefix#ingreds.deleted = '0'
		AND #prefix#ingreds.override_autocalc = '0'
		AND #prefix#orders.associated_id = '".$this->id."'
		AND #prefix#orders.id != '".$this->id."'
		AND #prefix#orders.operation='1'
		AND #prefix#orders.deleted='0'";
		if(!CONF_AUTOCALC_CONSIDERS_FIXED_PRICE) $query .= " AND #prefix#ingreds.price = '0'";
		
		$res=common_query($query,__FILE__,__LINE__);
		if (!$res) return mysql_errno();
		$added_num = mysql_num_rows($res);
		
		while ($arr = mysql_fetch_array($res)) {
			$added[] = $arr['id'];
		}

		$query = "SELECT #prefix#orders.* FROM `#prefix#orders` JOIN `#prefix#ingreds`
		WHERE #prefix#orders.ingredid = #prefix#ingreds.id
		AND #prefix#ingreds.deleted = '0'
		AND #prefix#ingreds.override_autocalc = '0'
		AND #prefix#orders.associated_id = '".$this->id."'
		AND #prefix#orders.id != '".$this->id."'
		AND #prefix#orders.operation='-1'
		AND #prefix#orders.deleted='0'";
		if(!CONF_AUTOCALC_CONSIDERS_FIXED_PRICE) $query .= " AND #prefix#ingreds.price = '0'";
		
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return mysql_errno();
		$removed_num = mysql_num_rows($res);
		
		while ($arr = mysql_fetch_array($res)) {
			$removed[] = $arr['id'];
		}

		$fixed = 0;
		if(CONF_AUTOCALC_CONSIDERS_FIXED_PRICE) {
			$query = "SELECT #prefix#orders.* FROM `#prefix#orders` JOIN `#prefix#ingreds`
			WHERE #prefix#orders.ingredid = #prefix#ingreds.id
			AND #prefix#ingreds.deleted = '0'
			AND #prefix#ingreds.override_autocalc = '0'
			AND #prefix#orders.associated_id = '".$this->id."'
			AND #prefix#orders.id != '".$this->id."'
			AND #prefix#orders.operation != '0'
			AND #prefix#orders.deleted='0'
			AND #prefix#ingreds.price != '0'";
			$res=common_query($query,__FILE__,__LINE__);
			if (!$res) return mysql_errno();
			$fixed = mysql_num_rows($res);
		}
		
		$calc_removed = get_conf(__FILE__,__LINE__,"autocalc_considers_removed");

		if($calc_removed) $mod_qty = $added_num - $removed_num - $fixed;
		else $mod_qty = $added_num - $fixed;
		
		$dish = new dish($this->data['dishid']);
		$skip_autocalc = $dish -> data['autocalc_skip'];

		if($mod_qty && $added_num) {
			$price_autocal = price_calc ($mod_qty,$skip_autocalc);
			$price_tot = $price_autocal * $this -> data['quantity'];
			$price_unitary = round($price_tot / $added_num,2);
			$price_err=round($price_tot-$price_unitary*$added_num,2);
			$price_corr=$price_unitary+$price_err;
		} else {
			$price_unitary = 0;
			$price_corr = 0;
		}
		
		if (isset($added) && is_array ($added)) {
			$query="UPDATE `#prefix#orders` SET `price`='".$price_unitary."' ";
			$query.=" WHERE (";
			foreach ($added as $value) {
				$query.="`id`='".$value."' OR ";
			}
			// strips the last comma and OR that has been put
			$query = substr ($query, 0, strlen($query)-4);
			$query.=")";
			
			$res2=common_query($query,__FILE__,__LINE__);
			if(!$res2) return ERR_MYSQL;
		
			$query="UPDATE `#prefix#orders` SET `price`='".$price_corr."' WHERE `id`='".$added[0]."'";
			$res2=common_query($query,__FILE__,__LINE__);
			if(!$res2) return ERR_MYSQL;
		}
		return 0;
	}

	function price_mods () {
		$err = 0;
		
		$dish = new dish ($this -> data['dishid']);
		$autocalc = $dish -> getAutocalc ();
		
		$only_notfree = false;
		if($autocalc) {
			if($err = $this -> price_mods_autocalc ()) return $err;
			$only_notfree = true;
		}
		if($err = $this -> price_mods_normal ($only_notfree)) return $err;
		
		return 0;
	}
   
	function price_main () {
		$query ="SELECT * FROM `#prefix#orders` WHERE `id` = '".$this->id."' AND `deleted`='0'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;

		$arr = mysql_fetch_array ($res);
		$dishid = $arr['dishid'];
		$ord_local_id = $arr ['id'];
		
		if($dishid == SERVICE_ID) $price_unitary = get_conf(__FILE__,__LINE__,"service_fee_price");
		else {
			$dish = new dish ($dishid);
			$price_unitary = $dish -> getPrice();
		}
		
		if ($dishid != SERVICE_ID && $dish -> getGeneric())
			return 0.0; 

		if ($this -> getOverridePrice())
			return 0; 

		$price = $price_unitary * $arr['quantity'];
		
		$query ="UPDATE `#prefix#orders` SET `price`='".$price."' WHERE `id` = '".$ord_local_id."' AND `deleted`='0'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;

		return 0;
	}
	
	function price_zero () {
		$price = 0;
		$query ="UPDATE `#prefix#orders` SET `price`='".$price."'
		WHERE `associated_id` = '".$this->id."'
		AND `deleted`='1'";
		$res=common_query($query,__FILE__,__LINE__);
		if(!$res) return ERR_MYSQL;
		return 0;
	}
	
	function price () {
		$err = 0;
		
		if ($this->id != $this -> data['associated_id']) return 0;

		if ($this -> data ['deleted']) {
			if($err = $this -> price_zero ()) return $err;
		} else {
			if($err = $this -> price_main ()) return $err;
			if($err = $this -> price_mods ()) return $err;
		}

		return $err;
	}
	
	function table_row($arr,$toclose,$cashier){
		$output = '';

		/*
		$dish = new dish ($arr['dishid']);
		$dishname = $dish -> name ($_SESSION['language']);
		$generic=$dish -> get ('generic');
		*/
		$dishname=$arr['dishname'];
		$generic=$arr['generic'];
		
		if(!$arr['deleted'] && $arr['printed']!=NULL && CONF_TIME_SINCE_PRINTED) {
			$dishname .= ' ('.orders_print_elapsed_time ($arr,true).')';
		}
		
		$deleted=$arr['deleted'];
		$orderid=$arr['id'];

		if ($arr['dishid']==MOD_ID){
			// first we clean the ingredient id from + and -
			$modingred=$arr['ingredid'];

			// then we find the ingredient name
			$ingr = new ingredient ($modingred);
			$moddeddishname = $ingr -> name ($_SESSION['language']);

			// say if it's added or subtracted
			if ($arr['operation']==1) {
				$dishname="&nbsp;&nbsp;&nbsp;&nbsp; ".ucphr('PLUS');
			} elseif ($arr['operation']==-1) {
				$dishname="&nbsp;&nbsp;&nbsp;&nbsp; ".ucphr('MINUS');
			} elseif ($arr['operation']==0) {
				$dishname="&nbsp;&nbsp;&nbsp;&nbsp; ";
			}

			$dishname.=" ".$moddeddishname;

			// and finally consider any optional info (lot/few)
			if($arr['ingred_qty']==1) {
				$dishname.=" ".ucphr('LOT');
			} elseif($arr['ingred_qty']==-1) {
				$dishname.=" ".ucphr('FEW');
			}

			// gets the original ingred price (from ingreds table)
			// if the original price is 0 and the actual price is 0
			// then it means that the ingred has passed through the autocalc system
			// and we let the waiter know this, so he could check the prices.
			$modingredprice = $ingr -> data['price'];
			//$modingredprice=get_db_data(__FILE__,__LINE__,$_SESSION['common_db'],"ingreds","price",$modingred);
			if($modingredprice==0 && $arr['price']!=0) {
				$dishname.=" (auto)";
			}
		} elseif ($arr['dishid']==SERVICE_ID){
			$dishname=ucfirst(phr('SERVICE_FEE'));
		}

		$classpriority=order_priority_class($arr['priority']);
		$oextra=order_extra_msg($arr['extra_care']);
		$class=order_printed_class($arr['printed'],$arr['suspend']);
		if(CONF_COLOUR_PRINTED && $arr['printed'] && !$arr['deleted']) {
			$classtime = order_print_time_class ($arr);
			if(!$classtime) $classtime=$class;
		} else $classtime = $class;
		$classextra=order_extra_class($arr['extra_care'],$class);

		// row begins
		$output .= '
	<tr bgcolor="'.$class.'">';
		
		// quantity cell
		if ($deleted && $arr['dishid']!=MOD_ID) {
			$output .= '
		<td bgcolor="'.$class.'">
			<s>'.$arr['quantity'].'</s>
		</td>';
		
		} elseif (!$deleted && $arr['dishid']!=MOD_ID) {
			$output .= '
		<td bgcolor="'.$class.'">
			'.$arr['quantity'].'
		</td>';
		
		} else {
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		
		}
		
		// mods cell
		if ($deleted && $arr['dishid']!=MOD_ID) {
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		
		} elseif (!$deleted
				&& $arr['printed']==NULL
				&& $arr['dishid']!=MOD_ID
				&& $arr['dishid']!=SERVICE_ID) {
			$link = 'orders.php?command=listmods&amp;data[id]='.$arr['associated_id'];
			$output .= '
		<td bgcolor="'.$class.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">+ -</a>
		</td>';

		
		} else {
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		
		}
		
		// Name of the dish
		if($deleted) {
			$output .= '
		<td bgcolor="'.$classtime.'">
			<s>'.$dishname.'</s>
		</td>';
		} else {
			if(!$deleted
				&& $arr['printed']==NULL
				&& $arr['dishid']!=MOD_ID
				&& $arr['dishid']!=SERVICE_ID) {
				$link = 'orders.php?command=listmods&amp;data[id]='.$orderid;
				$output .= '
		<td bgcolor="'.$classtime.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">'.$dishname.'</a>
		</td>';
			} elseif(!$deleted
				&& $arr['printed']==NULL
				&& $arr['dishid']==MOD_ID) {
				$link = 'orders.php?command=listmods&amp;data[id]='.$arr['associated_id'];
				$output .= '
		<td bgcolor="'.$classtime.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">'.$dishname.'</a>
		</td>';
			} else {
				$output .= '
		<td bgcolor="'.$classtime.'">
			'.$dishname.'
		</td>';
			}
		}
		
		if($deleted) {
			$output .= '
		<td bgcolor="'.$class.'">
			<s>'.$oextra.'</s>
		</td>';
		} else {
			$output .= '
		<td bgcolor="'.$classextra.'">
			'.$oextra.'
		</td>';
		}
		
		// priority cell
		$output .= '
		<td bgcolor="'.$classpriority.'">
			'.$arr['priority'].'
		</td>';
		

		// price cell
		if($generic && $cashier && $arr['printed'] && !$deleted) {
			$link = 'orders.php?command=price_modify&amp;data[id]='.$arr['id'];
			$output .= '
		<td bgcolor="'.$class.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">'.$arr['price'].'</a>
		</td>';
		} elseif($_SESSION['priceEditAllowed'][$_SESSION['sourceid']] && $cashier && $arr['dishid']!=MOD_ID && $arr['printed'] && !$deleted) {
			$link = 'orders.php?command=price_modify&amp;data[id]='.$arr['id'];
			$output .= '
		<td bgcolor="'.$class.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">'.$arr['price'].'</a>
		</td>';
		} elseif($deleted) {
			$output .= '
		<td bgcolor="'.$class.'">
			<s>'.$arr['price'].'</s>
		</td>';
		} else {
			$output .= '
		<td bgcolor="'.$class.'">
			'.$arr['price'].'
		</td>';
		}
	
		// edit button
		if($toclose){
			// the table has been closed, can't modify rows
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		} elseif (!$deleted
			&& $arr['printed']!=NULL
			&& $arr['dishid']!=MOD_ID) {
			// printed orderd, special edit (only deleting or substiting)
			$link = 'orders.php?command=edit&amp;data[id]='.$orderid;
			$output .= '
		<td bgcolor="'.$class.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">Edit</a>
		</td>';
		} elseif (!$deleted
			&& $arr['dishid']==MOD_ID) {
			// modification, can't edit directly, only via associated order
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		} elseif ($deleted)  {
			// deleted order, no editing, of course
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		} else {
			// other cases, normal editing
			$link = 'orders.php?command=edit&amp;data[id]='.$orderid;
			$output .= '
		<td bgcolor="'.$class.'" onclick="redir(\''.$link.'\');">
			<a href="'.$link.'">Edit</a>
		</td>';
		}

		// quantity arrows
		if ($toclose) {
			// table is closed, no more editing
			$output .= '
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>
		<td bgcolor="'.$class.'">
			&nbsp;
		</td>';
		} else {
			// normal section to rapidly add or subtract single quantities
			$output .= '
		<td bgcolor="'.$class.'">';
			if((!$arr['printed'] && $arr['dishid']!=MOD_ID) || $arr['dishid']==SERVICE_ID){
				$newquantity=$arr['quantity']+1;
				$link = 'orders.php?command=update&amp;data[quantity]='.$newquantity.'&amp;data[id]='.$orderid;
				if($arr['suspend']) $link .= '&amp;data[suspend]=1';
				if($arr['extra_care']) $link .= '&amp;data[extra_care]=1';
				$output .= '<a href="'.$link.'"><img src="'.IMAGE_PLUS.'" alt="'.ucphr('PLUS').' ('.ucphr('ADD').')" border=0></a></td>
		<td>';
				if($arr['quantity']>1){
					$newquantity=$arr['quantity']-1;
					$link = 'orders.php?command=update&amp;data[quantity]='.$newquantity.'&amp;data[id]='.$orderid;
					if($arr['suspend']) $link .= '&amp;data[suspend]=1';
					if($arr['extra_care']) $link .= '&amp;data[extra_care]=1';
					$output .= '<a href="'.$link.'"><img src="'.IMAGE_MINUS.'" alt="'.ucphr('MINUS').' ('.ucphr('REMOVE').')" border=0></a>';
				} elseif($arr['quantity']==1 && CONF_ALLOW_EASY_DELETE){
					$newquantity=0;
					$link = 'orders.php?command=ask_delete&amp;data[id]='.$orderid;
					if($arr['suspend']) $link .= '&amp;data[suspend]=1';
					if($arr['extra_care']) $link .= '&amp;data[extra_care]=1';
					$output .= '<a href="'.$link.'"><img src="'.IMAGE_LITTLE_TRASH.'" alt="'.ucphr('MINUS').' ('.ucphr('REMOVE').')" border=0></a>';
				} else {
					$output .= '&nbsp;'."\n";
				}
			} else {
				$output .= '
			&nbsp;</td>
			<td>&nbsp;';
			}
			$output .= '
		</td>';
		}
		$output .= '
	</tr>'."\n\n";

		return $output;
	}

	function line() {
		if(!$this->exists()) return -1;

		foreach ($this->data as $key => $value) {
			$output.=$key.' => '.$value.",<br/>\n";
		}

		$this->output=$output;
		return 0;
	}	
}


?>