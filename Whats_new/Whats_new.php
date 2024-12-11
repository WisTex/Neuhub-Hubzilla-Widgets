<?php

/**
 *   * Name: What's new
 *   * Description: Display recent channel post blurbs
 */

namespace Zotlabs\Widget;

class Whats_new {

	function widget(array $arr): string {

		$tpl_root = 'view/theme/' . \App::$config['system']['theme'];
		if(array_key_exists('tpl_root',$arr))
			$tpl_root = $arr['tpl_root'];
		
		$widget_title = "What's New";
		if(array_key_exists('widget_title',$arr))
			$widget_title = $arr['widget_title'];
		
		$channel_id = \App::$config['app']['primary_channel_id'] ?? 2;
		if(array_key_exists('channel_id',$arr) && intval($arr['channel_id']))
			$channel_id = intval($arr['channel_id']);
		if(! $channel_id)
			$channel_id = \App::$profile_uid;
		if(! $channel_id)
			return '';

		$num_posts = 1;
		if(array_key_exists('num_posts',$arr) && intval($arr['num_posts']))
			$num_posts = $arr['num_posts'];

		$blurb_length = 150;
		if(array_key_exists('blurb_length',$arr) && intval($arr['blurb_length']))
			$blurb_length = $arr['blurb_length'];

		$default_img = 'view/theme/' . \App::$config['system']['theme'] . '/img/whats_new/default.webp';
		if(array_key_exists('default_img',$arr))
			$default_img = $arr['default_img'];		

		if(array_key_exists('contains',$arr))
			$contains = $arr['contains'];

		$o = '';

		$r = q("SELECT item.*, channel.* FROM item
			JOIN channel ON item.author_xchan = channel.channel_hash
			WHERE channel.channel_id = %d AND item.item_private = 0 AND item.id <=> item.parent AND item.obj_type = 'Note' AND item.verb = 'Create' AND item.item_origin = 1 AND item.item_deleted = 0 AND item.item_hidden = 0 AND item.item_type = 0
			ORDER BY item.created DESC LIMIT %d",
			intval($channel_id),
			intval($num_posts)
		);

		if($r) {
			//die(print_r($r));
			$tpl = get_markup_template("whats_new.tpl", $tpl_root);
			if ($tpl) {
				// SEO addon installed and activated?
				$addons = \Zotlabs\Lib\Config::get('system', 'addon', '');
				if (!empty($addons)) {
					$addons = array_flip(explode(", ", $addons));
					//die(print_r($addons));
					if (isset($addons['seo'])) {
						require_once('addon/seo/seo.php');
					}
				}

				$o = replace_macros($tpl, [
					'$widget_title' => $widget_title,
					'$posts' => array_map(function($post) use($blurb_length) {
						$imgPattern = '/<img .*?src="([^"]+)"[^>]*>/s';
						if (preg_match($imgPattern, bbcode($post['body']), $matches) == 1) {
							// Use the first image found in post
							$post['image'] = $matches[1];
						}
						$post['blurb'] = $this->ellipsify(strip_tags(bbcode($post['body'])), $blurb_length);
						$post['created'] = strtotime($post['created']);
						$post['postUrl'] = (class_exists('SEO')) ? z_root() . "/" . \SEO::generatePermalink($post) : z_root() . "/channel/" . $post['channel_address'] . "?mid=" . $post['uuid'];
						return $post;
					}, $r),
					'$default_img' => z_root() . "/" . $default_img
				]);
			} else {
				$o .= '<div style="padding: 1rem 0"><div class="card" style="padding: 1rem"><h2 style="margin: 1rem 0; border-bottom: 1px #ccc solid;">' . $widget_title . '</h2>';
				foreach ($r as $post) {
					$o .= '<div class="widget bblock thread-wrapper">';
					if($post['title'])
						$o .= '<h3><a href="' . $post['mid'] . '">' . $post['title'] . '</a></h3>';
		
					$o .= $this->ellipsify(strip_tags(bbcode($post['body'])), $blurb_length);
					$o .= ' <a href="' . $post['mid'] . '">READ MORE</a>';
					$o .= '</div>';
				}
				$o .= '</div></div>';
			}
		}

		return $o;
	}

	private function ellipsify($s, $maxlen): string {
		if($maxlen & 1)
			$maxlen --;
		if($maxlen < 4)
			$maxlen = 4;
	
		if(mb_strlen($s) < $maxlen)
			return $s;
	
		return mb_substr(strip_tags($s), 0, $maxlen) . '...';
	}
}
