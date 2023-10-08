<?php if (!defined('APS_VER')) exit('restricted access');
/*
 * @package WordPress
 * @subpackage APS Products
 * @class APS_Product
*/

class APS_Product {
	
	// prepare variables
	public $pid,
	$cats = '',
	$cat_id = '',
	$currency = '',
	$tabs_display = '',
	$settings_general = '',
	$settings_design = '',
	$images_settings = '',
	$zoom_settings = '',
	$tabs_settings = '',
	$lightbox_settings = '',
	$rating_bars = '',
	$bars_data = '';
	
	// initialize variables on construct
	public function __construct($id) {
		if ($id) {
			// product id (pid)
			$this->pid = $id;
			// general settings
			$this->settings_general = get_aps_settings('settings');
			// design settings
			$this->settings_design = get_aps_settings('design');
			// get zoom settings
			$this->zoom_settings = get_aps_settings('zoom');
			// get gallery (lightbox) settings
			$this->lightbox_settings = get_aps_settings('gallery');
			// base currency
			$this->currency = aps_get_base_currency();
			// images settings
			$this->images_settings = get_aps_settings('store-images');
			// tabs settings
			$this->tabs_settings = get_aps_settings('tabs');
			// get categories
			$this->cats = get_product_cats($this->pid);
			$this->cat_id = ($this->cats[0]) ? $this->cats[0]->term_id : '';
			// rating bars data
			$this->bars_data = get_aps_rating_bars_data();
			$this->rating_bars = get_aps_cat_bars($this->cat_id);
		}	
	}
	
	// product
	public function product() {
		// try to retrive product from wp_cache
		$product = wp_cache_get($this->pid, 'products');
		
		if (!$product) {
			$product = $this->get_product();
			// add product to wp_cache
			wp_cache_add($this->pid, $product, 'products');
		}
		// return the product data
		return $product;
	}
	
	// get product (post type aps-products)
	protected function get_product() {
		$post = get_post($this->pid);
		
		if ($post) {
			$brand = get_product_brand($this->pid);
			$comments_count = $post ? $post->comment_count : 0;
			
			// create an array of product data
			$product = array(
				'title' => $post->post_title,
				'date' => $post->post_date,
				'cats' => $this->cats,
				'cat' => $this->cats[0],
				'cat_id' => $this->cat_id,
				'brand' => $brand,
				'link' => get_permalink($this->pid),
				'excerpt' => $post->post_excerpt,
				'content' => $post->post_content,
				'reviews_num' => $comments_count
			);
			return $product;
		}
	}
	
	// get general data of the product
	private function get_general_data() {
		return get_aps_product_general_data($this->pid);
	}
	
	// product price
	public function price() {
		$general = $this->get_general_data();
		
		if ($general['price'] > 0) {
			$price = aps_get_product_price($this->currency, $general);
			$item_on_sale = aps_product_on_sale($general);
			$price_formated = aps_format_product_price($this->currency, $general['price']);
			
			$pricing = array(
				'sku' => $general['sku'],
				'qty' => $general['qty'],
				'price' => $general['price'],
				'price_formated' => $price_formated,
				'currency' => $this->currency,
				'stock' => $general['stock'],
				'on_sale' => $item_on_sale,
			);
			
			if ($item_on_sale) {
				$sale_price_formated = aps_format_product_price($this->currency, $general['sale-price']);
				$calc_discount = aps_calc_discount($general['price'], $general['sale-price']);
				$discount_price = aps_format_product_price($this->currency, $calc_discount['discount']);
				// convert to unix timestamp
				$sale_start = aps_get_timestamp($general['sale-start']);
				$sale_end = aps_get_timestamp($general['sale-end']);
				
				$pricing['sale_price'] = $general['sale-price'];
				$pricing['sale_price_formated'] = $sale_price_formated;
				$pricing['sale_start'] = $sale_start;
				$pricing['discount'] = $calc_discount['discount'];
				$pricing['discount_formated'] = $discount_price;
				$pricing['discount_percetage'] = $calc_discount['percent'];
				$pricing['sale_end'] = $sale_end;
			}
			return $pricing;
		}
	}
	
	// product featured image
	public function image() {
		// get image sizes from settings
		$width = $this->images_settings['single-image']['width'];
		$height = $this->images_settings['single-image']['height'];
		$crop = $this->images_settings['single-image']['crop'];
		$image = get_product_image($width, $height, $crop, $this->pid);
		return $image;
	}
	
	// product image gallery
	public function gallery() {
		// get image sizes from settings
		$thumb_width = $this->images_settings['product-thumb']['width'];
		$thumb_height = $this->images_settings['product-thumb']['height'];
		$thumb_crop = $this->images_settings['product-thumb']['crop'];
		$single_width = $this->images_settings['single-image']['width'];
		$single_height = $this->images_settings['single-image']['height'];
		$single_crop = $this->images_settings['single-image']['crop'];
		
		$images = get_aps_product_gallery($this->pid);
		$featured_img_id = get_post_thumbnail_id($this->pid);
		array_unshift($images, $featured_img_id);
		$gallery = array();
		
		if (aps_is_array($images)) {
			foreach ($images as $image) {
				$thumb = get_product_image($thumb_width, $thumb_height, $thumb_crop, '', (int) $image);
				$large = get_product_image($single_width, $single_height, $single_crop, '', (int) $image);
				$alt = ($alt = get_post_meta((int) $image, '_wp_attachment_image_alt', true)) ? $alt : '';
				$gallery[] = array(
					'thumb' => $thumb,
					'large' => $large,
					'alt' => $alt
				);
			}
			return $gallery;
		}
	}
	
	// get product features
	public function features() {
		$features = get_aps_product_features($this->pid);
		
		if (aps_is_array($features)) {
			$features_array = array();
			foreach ($features as $feature) {
				$features_array[] = array(
					'name' => isset($feature['name']) ? $feature['name'] : '',
					'icon' => isset($feature['icon']) ? $feature['icon'] : '',
					'value' => isset($feature['value']) ? $feature['value'] : ''
				);
			}
			return $features_array;
		}
	}
	
	// get product in compare list or not
	public function in_compare() {
		// get comps lists
		$comp_lists = aps_get_compare_lists();
		$in_comps = aps_product_in_comps($comp_lists, $pid);
		
		$in_compare = array(
			'in' => ($in_comps) ? true : false,
			'label' => ($in_comps) ? esc_html__('Remove from Compare', 'aps-text') : esc_html__('Add to Compare', 'aps-text')
		);
		
		return $in_compare;
	}
	
	// tabs display
	public function tabs() {
		$tabs = $this->tabs_settings;
		$tabs_data = get_aps_product_tabs($this->pid);
		
		$tabs_display = array();
		foreach ($tabs as $tab_key => $tab) {
			if ($tab['display'] === 'yes') {
				switch ($tab_key) {
					// tab overview
					case 'overview' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => true);
					break;
					// tab specs
					case 'specs' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (aps_is_array($this->groups())) ? true : false);
					break;
					// tab reviews
					case 'reviews' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => true);
					break;
					// tab videos
					case 'videos' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (aps_is_array($this->videos())) ? true : false);
					break;
					// tab offers
					case 'offers' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (aps_is_array($this->offers())) ? true : false);
					break;
					// tab custom1
					case 'custom1' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (!empty($tabs_data['tab1'])) ? true : false, 'content' => (isset($tabs_data['tab1'])) ? $tabs_data['tab1']: '');
					break;
					// tab custom2
					case 'custom2' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (!empty($tabs_data['tab2'])) ? true : false, 'content' => (isset($tabs_data['tab1'])) ? $tabs_data['tab2']: '');
					break;
					// tab custom3
					case 'custom3' :
						$tabs_display[$tab_key] = array('name' => $tab['name'], 'display' => (!empty($tabs_data['tab2'])) ? true : false, 'content' => (isset($tabs_data['tab1'])) ? $tabs_data['tab3']: '');
					break;
				}
			}
		}
		return $tabs_display;
	}
	
	// groups
	public function groups() {
		// get attributes groups by category
		$groups = get_aps_cat_groups($this->cat_id);
		return $groups;
	}
	
	// attributes with groups
	public function attributes() {
		// get attributes groups by category
		$groups = $this->groups();
		$groups_data = get_aps_groups_data();
		$attrs_data = get_aps_attributes_data();
		
		// start groups loop
		if ($groups) {
			$groups_out = array();
			foreach ($groups as $group) {
				$group_data = $groups_data[$group];
				$group_slug = $group_data['slug'];
				$group_attrs = get_aps_group_attributes($group);
				
				// get product attributes
				$attributes = get_aps_product_attributes($this->pid, $group);
				$has_attrs = false;
				
				// check if data is an array
				if (aps_is_array($group_attrs)) {
					$has_attrs = true;
					$attrs_array = array();
					$attrs_infold = array();
					
					foreach ($group_attrs as $attr_id) {
						// get attribute data
						$attr_data = $attrs_data[$attr_id];
						$attr_meta = $attr_data['meta'];
						$attr_info = $attr_data['desc'];
						$attr_infold = ($attr_data['infold']) ? $attr_data['infold'] : 'no';
						$value = (isset($attributes[$attr_id])) ? $attributes[$attr_id] : null;
						
						if ($value) {
							if ($attr_infold == 'no') {
								$attrs_array[$attr_id] = array(
									'name' => $attr_data['name'],
									'slug' => $attr_data['slug'],
									'type' => $attr_meta['type'],
									'info' => $attr_info,
									'value' => $value
								);
							} else {
								$attrs_infold[$attr_id] = array(
									'name' => $attr_data['name'],
									'slug' => $attr_data['slug'],
									'type' => $attr_meta['type'],
									'info' => $attr_info,
									'value' => $value
								);
							}
						}
					}
				}
				$groups_out[$group_slug] = array(
					'id' => $group,
					'name' => $group_data['name'],
					'icon' => $group_data['icon'],
					'attrs' => $attrs_array,
					'infold' => $attrs_infold,
					'has_attrs' => $has_attrs
				);
			}
			return $groups_out;
		}
	}
	
	// product videos
	public function videos() {
		$videos = get_aps_product_videos($this->pid);
		
		// loop videos
		if (aps_is_array($videos)) {
			$videos_array = array();
			foreach ($videos as $video) {
				$host = (isset($video['host'])) ? $video['host'] : '';
				$vid = (isset($video['vid'])) ? $video['vid'] : '';
				$img = (isset($video['img'])) ? str_replace( 'http://', 'https://', $video['img'] ) : '';
				$title = (isset($video['title'])) ? $video['title'] : '';
				$length = (isset($video['length'])) ? $video['length'] : '';
				
				switch ($host) {
					case 'youtube':
						$video_url = 'https://www.youtube.com/watch?v=' .$vid;
					break;
					case 'vimeo':
						$video_url = 'https://www.vimeo.com/' .$vid;
					break;
					case 'dailymotion':
						$video_url = 'https://www.dailymotion.com/embed/video/' .$vid;
					break;
				}
				$videos_array[] = array(
					'host' => $host,
					'id' => $vid,
					'url' => $video_url,
					'img' => $img,
					'title' => $title,
					'length' => $length
				);
			}
			return $videos_array;
		}
	}
	
	// product offers
	public function offers() {
		$stores = get_aps_affiliates();
		$offers = get_aps_product_offers($this->pid);
		
		if (aps_is_array($offers)) {
			$offers_array = array();
			foreach ($offers as $offer) {
				$store = (isset($stores[$offer['store']])) ? $stores[$offer['store']] : null;
				if ($store) {
					$offers_array[] = array(
						'title' => (isset($offer['title'])) ? $offer['title'] : '',
						'price' => (isset($offer['price'])) ? $offer['price'] : '',
						'url' => (isset($offer['url'])) ? $offer['url'] .$store['id'] : '',
						'store_name' => (isset($store['name'])) ? $store['name'] : '',
						'store_logo' => (isset($store['logo'])) ? $store['logo'] : ''
					);
				}
			}
			return $offers_array;
		}
	}
	
	// product rating by editor
	public function ratings() {
		// get product rating
		$product_rating = get_product_rating($this->pid);
		$total_bar = get_product_rating_total($this->pid);
		$total_color = aps_rating_bar_color(round($total_bar));
		$rating_display = (isset($product_rating['show_bars'])) ? $product_rating['show_bars'] : 'yes';
		$animate = (isset($this->settings_general['rating-anim'])) ? $this->settings_general['rating-anim'] : 'yes';
		
		$ratings_out = array(
			'display' => $rating_display,
			'animate' => $animate,
			'total_bar' => floatval($total_bar),
			'total_color' => $total_color,
			'total_stars' => number_format(($total_bar / 2), 1)
		);
		
		if (aps_is_array($this->rating_bars)) {
			foreach ($this->rating_bars as $bar) {
				$bar_data = $this->bars_data[$bar];
				$bar_slug = $bar_data['slug'];
				$bar_name = $bar_data['name'];
				$rating = (!empty($product_rating[$bar_slug])) ? $product_rating[$bar_slug] : $bar_data['val'];
				$color = aps_rating_bar_color($rating);
				
				$ratings_out['bars'][] = array(
					'id' => $bar,
					'name' => $bar_name,
					'slug' => $bar_slug,
					'rating' => $rating,
					'color' => $color
				);
			}
		}
		return $ratings_out;
	}
	
	public function reviews() {
		$animate = (isset($this->settings_general['rating-anim'])) ? $this->settings_general['rating-anim'] : 'yes';
		$user_rating = (isset($this->settings_general['user-rating'])) ? $this->settings_general['user-rating'] : 'yes';
		
		$args = array(
			'post_id' => $this->pid,
			'type' => 'review',
			'order' => 'ASC',
			'status' => 'approve'
		);
		
		$reviews = get_comments($args);
		
		// check if product have reviews
		if ( $reviews ) {
			if ($user_rating === 'yes') {
				$count = 0;
				$total = 0;
				
				$overall = array();
				if ($this->rating_bars) {
					foreach ($this->rating_bars as $bar) {
						$bar_key = $this->bars_data[$bar]['slug'];
						$bar_name = $this->bars_data[$bar]['name'];
						$overall[$bar_key] = array(
							'id' => $bar,
							'name' => $bar_name,
							'value' => 0
						);
					}
				}
				
				$review_out = array();
				foreach ($reviews as $review) {
					$cid = $review->comment_ID;
					$review_date = get_comment_date('', $cid);
					$review_time = get_comment_date('g:i a', $cid);
					$rev_publish = get_comment_date('Y-m-d', $cid);
					$author = $review->comment_author;
					$author_email = $review->comment_author_email;
					// reviewer photo fallback
					$rev_fl = strtolower(substr($author, 0, 1));
					$rev_photo = APS_URL .'img/avt/' .$rev_fl .'.png';
					$reviewRating = get_comment_meta($cid, 'aps-review-rating', true);
					$review_title = get_comment_meta($cid, 'aps-review-title', true);
					$review_content = $review->comment_content;
					
					$sub_total = 0;
					$rev_total = 0;
					$bar_count = 0;
					
					if ($this->rating_bars) {
						$rating = array();
						foreach ($this->rating_bars as $bar) {
							$bar_key = $this->bars_data[$bar]['slug'];
							$bar_name = $this->bars_data[$bar]['name'];
							$value = (!empty($reviewRating[$bar_key])) ? floatval($reviewRating[$bar_key]) : $this->bars_data[$bar]['val'];
							$bar_color = aps_rating_bar_color(round($value));
							$overall[$bar_key]['value'] += $value;
							$sub_total += $value;
							$bar_count++;
							$rating[] = array(
								'id' => $bar,
								'slug' => $bar_key,
								'name' => $bar_name,
								'value' => $value,
								'color' => $bar_color
							);
						}
					
						$total += $sub_total / $bar_count;
						$rev_total += $sub_total / $bar_count;
						$total_color = aps_rating_bar_color(round($rev_total));
						$count++;
						
						$review_out[] = array(
							'id' => $cid,
							'date' => $review_date,
							'time' => $review_time,
							'publish' => $rev_publish,
							'total' => $rev_total,
							'color' => $total_color,
							'rating' => $rating,
							'title' => $review_title,
							'content' => $review_content,
							'author' => $author,
							'author_email' => $author_email,
							'author_photo' => $rev_photo
						);
					}
				}
				
				foreach ($overall as $ov_key => $ov_val) {
					$ov_rating = floatval(number_format($ov_val['value'] / $count, 1));
					$ov_color = aps_rating_bar_color(round($ov_rating));
					$overall[$ov_key]['value'] = $ov_rating;
					$overall[$ov_key]['color'] = $ov_color;
				}
				
				$overall_bar = $total / $count;
				$overall_bar = floatval(number_format($overall_bar, 1));
				$overall_color = aps_rating_bar_color(round($overall_bar));
				
				// reviews array with ratings
				$reviews_out = array(
					'overall_bar' => $overall_bar,
					'overall_color' => $overall_color,
					'ratings' => $overall,
					'reviews_count' => $count,
					'reviews' => $review_out
				);
				
				return $reviews_out;
			}
		}
		
	}
	
}
