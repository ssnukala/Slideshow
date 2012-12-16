<?php
/**
 * Class SlideslowPlugin is called whenever a slideshow do_action tag is come across.
 * Responsible for outputting the slideshow's HTML, CSS and Javascript.
 *
 * @author: Stefan Boonstra
 * @version: 06-12-12
 */
class SlideshowPlugin {

	/** int $sessionCounter */
	private static $sessionCounter = 0;

	/**
	 * Function deploy prints out the prepared html
	 *
	 * @param int $postId
	 */
	static function deploy($postId = null){
		echo self::prepare($postId);
	}

	/**
	 * Function prepare returns the required html and enqueues
	 * the scripts and stylesheets necessary for displaying the slideshow
	 *
	 * Passing this function no parameter or passing it a negative one will
	 * result in a random pick of slideshow
	 *
	 * @param int $postId
	 * @return String $output
	 */
	static function prepare($postId = null){
		// Get post by its ID, if the ID is not a negative value
		if(is_numeric($postId) && $postId >= 0)
			$post = get_post($postId);

		// Get slideshow by slug when it's a non-empty string
		if(is_string($postId) && !is_numeric($postId) && !empty($postId)){
			$query = new WP_Query(array(
				'post_type' => SlideshowPluginPostType::$postType,
				'name' => $postId,
				'orderby' => 'post_date',
				'order' => 'DESC',
				'suppress_filters' => true
			));

			if($query->have_posts())
				$post = $query->next_post();
		}

		// When no slideshow is found, get one at random
		if(empty($post)){
			$post = get_posts(array(
				'numberposts' => 1,
				'offset' => 0,
				'orderby' => 'rand',
				'post_type' => SlideshowPluginPostType::$postType
			));

			if(is_array($post))
				$post = $post[0];
		}

		// Exit on error
		if(empty($post))
			return '<!-- Wordpress Slideshow - No slideshows available -->';

		// Log slideshow's issues to be able to track them on the page.
		$log = array();

		// Get slides
		$slides = SlideshowPluginSettingsHandler::getSlides($post->ID);
		if(!is_array($slides) || count($slides) <= 0)
			$log[] = 'No slides were found';

		// Get settings
		$settings = SlideshowPluginSettingsHandler::getSettings($post->ID);
		$styleSettings = SlideshowPluginSettingsHandler::getStyleSettings($post->ID);

		// Randomize if setting is true.
		if(isset($settings['random']) && $settings['random'] == 'true')
			shuffle($slides);

		// Enqueue functional sheet
		wp_enqueue_style(
			'slideshow_functional_style',
			SlideshowPluginMain::getPluginUrl() . '/style/' . __CLASS__ . '/functional.css',
			array(),
			SlideshowPluginMain::$version
		);

		// The slideshow's session ID, allows JavaScript and CSS to distinguish between multiple slideshows
		$sessionID = self::$sessionCounter++;

		// Get stylesheet for printing
		$style = '';
		if($styleSettings['style'] == 'custom' && isset($styleSettings['custom']) && !empty($styleSettings['custom'])){ // Custom style
			$style = str_replace('%plugin-url%', SlideshowPluginMain::getPluginUrl(), $styleSettings['custom']);
		}else{ // Set style
			$filePath = SlideshowPluginMain::getPluginPath() . '/style/' . __CLASS__ . '/style-' . $styleSettings['style'] . '.css';
			if(file_exists(SlideshowPluginMain::getPluginPath() . '/style/' . __CLASS__ . '/style-' . $styleSettings['style'] . '.css')){
				ob_start();
				include($filePath);
				$style = str_replace('%plugin-url%', SlideshowPluginMain::getPluginUrl(), ob_get_clean());
			}
		}

		// Append the random ID to the slideshow container in the stylesheet, to identify multiple slideshows
		if(!empty($style))
			$style = str_replace('.slideshow_container', '.slideshow_container_' . $sessionID, $style);

		// Include output file to store output in $output.
		$output = '';
		ob_start();
		include(SlideshowPluginMain::getPluginPath() . '/views/' . __CLASS__ . '/slideshow.php');
		$output .= ob_get_clean();

		// Enqueue slideshow script
		wp_enqueue_script(
			'slideshow-jquery-image-gallery-script',
			SlideshowPluginMain::getPluginUrl() . '/js/' . __CLASS__ . '/slideshow.js',
			array(
                'jquery',
                'swfobject'
            ),
			SlideshowPluginMain::$version
		);

		// Include slideshow settings by localizing them
		wp_localize_script(
			'slideshow-jquery-image-gallery-script',
			'SlideshowPluginSettings_' . $sessionID,
			$settings
		);

		// Return output
		return $output;
	}
}