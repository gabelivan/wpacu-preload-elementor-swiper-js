<?php
/*
Plugin Name: Asset CleanUp: Preload Elementor's Swiper (.js)
Plugin URI: https://github.com/gabelivan/wpacu-preload-swiper-js
Description: This plugin checks if there are Swiper elements in the HTML source and preload swiper.(min).js (to improve "Preload key requests" in PageSpeed Insights). This is recommended to use ONLY when swiper.(min).js is loaded from another JS file and can't be preloaded via Asset CleanUp because it doesn't show in the list of assets to manage
Author: Gabriel Livan
Version: 1.1
Author URI: https://gabelivan.com/
*/
if ( ! defined('ABSPATH') ) {
	return;
}

add_filter('wpacu_html_source_before_optimization', function($htmlSource) {
	if (! defined('ELEMENTOR_ASSETS_URL')) {
		return $htmlSource; // "Elementor" plugin is not loaded
	}

	$checkIfClassContains = array('swiper-container', 'swiper-wrapper');

	// List all the items from the array separated by | making sure that any RegEx characters with special meaning are quoted via preg_quote()
	// More information: https://www.php.net/manual/en/function.preg-quote.php
	$containsListRegEx = implode( '|', array_map( function( $value ) {
			return preg_quote( $value, '/' );
		}, $checkIfClassContains )
	);

	// This RegEx looks inside a class' content (either within single or double quote) and checks if it has the classes mentioned at $checkIfClassContains
	$verifyRegEx = '#class=("|\').*('.$containsListRegEx.')(.*?)("|\')(.*?)>#';

	preg_match_all($verifyRegEx, $htmlSource, $anyMatches);

	// Only continue if, all classes where found, continue
	if (isset($anyMatches[0]) && count($anyMatches[0]) >= count($checkIfClassContains)) {
		// Check if it wasn't already preloaded (e.g. bu another plugin
		// or perhaps a custom code inserted into function.php from the child theme)
		$swiperJsUrl = wpacuGetSwiperJsUrl();

		if ( ! $swiperJsUrl ) {
			return $htmlSource; // the URL to Swiper JS could not be determined, so, stop here
		}

		$linkSwiperPreload = "\n".'<link rel="preload" href="'.$swiperJsUrl.'" crossorigin="anonymous" as="script" />'."\n";

		$htmlPreloadSignature = \WpAssetCleanUp\Preloads::DEL_SCRIPTS_PRELOADS;

		if (strpos($htmlSource, $htmlPreloadSignature) !== false) {
			$htmlSource = str_replace( $htmlPreloadSignature, $linkSwiperPreload . $htmlPreloadSignature, $htmlSource );
		} else {
			// Fallback, not as smooth, but should work fine
			$htmlSource = preg_replace('#</head>(.*?)<body#si', $linkSwiperPreload.'</head>$1<body', $htmlSource);
		}
	}

	return $htmlSource;
}, PHP_INT_MAX);

/**
 * @return false|string
 */
function wpacuGetSwiperJsUrl()
{
	$swiperJsFileUrl = ELEMENTOR_ASSETS_URL.'lib/swiper/swiper';

	$swiperJsFileDir = ELEMENTOR_PATH.'assets/lib/swiper/';

	$swiperAssetExists = (is_file($swiperJsFileDir.'swiper.js') || is_file($swiperJsFileDir.'swiper.min.js'));

	if ( ! $swiperAssetExists ) {
		return false;
	}

	$isTestMode = (defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) || (defined( 'ELEMENTOR_TESTS' ) && ELEMENTOR_TESTS);

	if (! $isTestMode) {
		$swiperJsFileUrl .= '.min.js'; // load the minified version if there's no test mode
	} else {
		$swiperJsFileUrl .= '.js'; // test mode is enabled, thus load it non-minified
	}

	// Determine Swiper Version / The URL has to be exactly the same one loaded from /wp-content/plugins/elementor/assets/js/frontend.(min).js
	$frontEndMinJsFilePath = ELEMENTOR_ASSETS_PATH.'js/frontend.min.js';

	if (! is_file($frontEndMinJsFilePath)) {
		return false;
	}

	$frontEndMinJsContents = file_get_contents(ELEMENTOR_ASSETS_URL.'js/frontend.min.js');

	preg_match_all('#assets,"lib/swiper/swiper"\)(.*?)\".js\?ver=(.*?)\"\)#si', $frontEndMinJsContents, $verMatches);

	if (isset($verMatches[2][0]) && is_numeric(str_replace('.', '', $verMatches[2][0]))) {
		$swiperJsFileUrl .= '?ver='.trim($verMatches[2][0]);
	}

	return $swiperJsFileUrl;
}
