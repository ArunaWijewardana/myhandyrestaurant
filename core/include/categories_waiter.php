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

function categories_printed ($sourceid,$category) {
	$catprinted=array();
	$catprintedtext=get_db_data(__FILE__,__LINE__,$_SESSION['common_db'],'sources',"catprinted",$sourceid);
	if($catprintedtext!=""){
		$catprinted = explode (" ", $catprintedtext);
	}

	// the priority has already been printed. return true
	if(in_array($category,$catprinted)) return true;
	
	return 0;
}

function categories_orders_present ($sourceid,$category) {
	$query = "	SELECT id
				FROM #prefix#orders
				WHERE sourceid ='".$sourceid."'
				AND priority =$category
				AND deleted = 0
				AND printed IS NOT NULL
				AND dishid != ".MOD_ID."
				AND dishid != ".SERVICE_ID."
				AND suspend = 0";
	$res = common_query($query,__FILE__,__LINE__);
	if(!$res) return ERR_MYSQL;
	
	return mysql_num_rows($res);
}

function categories_list ($data=''){
	$output = '
<table bgcolor="'.COLOR_TABLE_GENERAL.'">
';

	$table = "#prefix#categories";
	$lang_table = "#prefix#categories_".$_SESSION['language'];
	
	$query="SELECT
		$table.`id`,
		IF($lang_table.`table_name`='' OR $lang_table.`table_name` IS NULL,$table.`name`,$lang_table.`table_name`) as `name`,
		$table.htmlcolor
		FROM `$table`
		LEFT JOIN `$lang_table` ON $lang_table.`table_id`=$table.`id`
		WHERE $table.`deleted`='0'
		ORDER BY $table.id ASC
		";
	
	$res=common_query($query,__FILE__,__LINE__);
	if(!$res) return '';
	
	$i=0;
	while ($arr = mysql_fetch_array ($res)) {
		$i++;
		$catid=$arr['id'];
		$name=ucfirst($arr['name']);
		
		$backcommand="order_create1";
		$bgcolor=$arr['htmlcolor'];
		
		$link = 'orders.php?command=dish_list&amp;data[category]='.$catid;
		if(isset($data['quantity']) && $data['quantity']) $link .= '&amp;data[quantity]='.$data['quantity'];
		if(isset($data['priority']) && $data['priority']) $link .= '&amp;data[priority]='.$data['priority'];
		
		if($i%2) {
			$output .= '
	<tr>';
		}
		
		$output .= '
		<td bgcolor="'.$bgcolor.'" onclick="redir(\''.$link.'\');return(false);">
		<a href="'.$link.'">
		<strong>'.$name.'</strong>
		</a>
		</td>';

		if(($i+1)%2) {
			$output .= '
	</tr>';
		}
	}
	$output .= '
	</tbody>
</table>';

	return $output;
}

function letters_list_creator (){
	$invisible_show = get_conf(__FILE__,__LINE__,"invisible_show");
	if($invisible_show) {
		$query="SELECT `name`, `table_name` FROM `#prefix#dishes#lang#`
			JOIN #prefix#dishes
			WHERE #prefix#dishes#lang#.table_id=#prefix#dishes.id
			AND #prefix#dishes.deleted='0'";
	} else {
		$query="SELECT `name`, `table_name` FROM `#prefix#dishes#lang#`
			JOIN #prefix#dishes
			WHERE `visible`='1'
			AND #prefix#dishes#lang#.table_id=#prefix#dishes.id
			AND #prefix#dishes.deleted='0'";
	}

	$res=common_query($query,__FILE__,__LINE__);
	if(!$res) return ERR_MYSQL;
	$dishes_letters = array();
	while ($arr = mysql_fetch_array ($res)) {
		$name = trim($arr['table_name']);
		if ($name == null || strlen($name) == 0)
			$name = trim($arr['name']); //if no name in the fixed lang, use the main name
		array_push($dishes_letters, substr($name, 0, 1));
	}
	return $dishes_letters;
}

function letters_list ($data=''){
	$output = '
<table bgcolor="'.COLOR_TABLE_GENERAL.'">
';

	$output .= '
	<tr>';
	
	// letters
	// total 32-95
	$offset = 32;
	
	$col=-1;
	$color = 0;
	
	$dishes_letters = letters_list_creator ();
	
	for ($i=17;$i<=(92-$offset);$i++) {

		$letter = chr($i + $offset);
		if($letter == "'") $letter = "\'";
		
		if($letter =='%' ) continue;
		
		$bgcolor=COLOR_TABLE_GENERAL;
		//RTG: if there is some dishes begginnig with this letter
		if(in_array($letter, $dishes_letters, false)) {
			$letter= htmlentities($letter);
			$link = 'orders.php?command=dish_list&amp;data[letter]='.$letter;
			
			if(isset($data['quantity']) && $data['quantity']) $link .= '&amp;data[quantity]='.$data['quantity'];
			if(isset($data['priority']) && $data['priority']) $link .= '&amp;data[priority]='.$data['priority'];
			
			$bgcolor = color ($color++);
			$output .= '
			<td bgcolor="'.$bgcolor.'" onclick="redir(\''.$link.'\');return(false);">
			<a href="'.$link.'">
			<strong>'.$letter.'</strong>
			</a>
			</td>';
			$col++;
		} else {
			continue;
			$output .= '
			<td bgcolor="'.$bgcolor.'">
			&nbsp;
			</td>';
		}
			
		if((($col +1) % 6) == 0) {
			$color++;
			$output .= '
		</tr>
		<tr>';
		}
	}
	
	$output .= '
	</tr>';
	
	$output .= '
	</tbody>
</table>';

	return $output;
}

?>