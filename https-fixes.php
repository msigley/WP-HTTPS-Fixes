<?php
/*
Plugin Name: HTTPS fixes
Version: 2.1.0
Author: Matthew Sigley
*/

// Don't verify certificates on requests to localhost
add_filter( 'https_local_ssl_verify', '__return_false', 999 );

$cainfo_file = ini_get( 'curl.cainfo' );
$openssl_cainfo_file = ini_get( 'openssl.cafile' );
if( empty( $cainfo_file ) || empty( $openssl_cainfo_file ) || !file_exists( $cainfo_file ) || !file_exists( $openssl_cainfo_file ) ) {
	// Don't verify certificates on requests if the cainfo file is missing
	add_filter( 'https_ssl_verify', '__return_false', 999 );
} else {
	// Force Wordpress to use cainfo file in the php.ini
	add_filter( 'http_request_args', function( $request_args ) use ( $cainfo_file ) {
		$request_args['sslcertificates'] = $cainfo_file;
		return $request_args;
	}, 999 );
}

// Force HTTPS on post thumbnail urls
function force_url_https( $url ) {
	if( substr( $url, 0, 7 ) != 'http://' )
		return $url;
	
	return 'https://' . substr( $url, 7 );
}
add_filter( 'wp_get_attachment_url', 'force_url_https', 999 );

// Force HTTPS on Image srcset attributes
function force_srcset_https( $sources ) {
	foreach ( $sources as &$source )
		$source['url'] = force_url_https( $source['url'] );

	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'force_srcset_https', 999 );

function force_img_src_https_in_post_content($post_data){
	if( empty( $post_data['post_content'] ) )
		return $post_data;

	$original_content = $content = $post_data['post_content'];
	$html_tags = array();
	$marker_num = 0;
	$marker_format = "{{[[%d]]}}";
	while( preg_match('/<[^>]*>/', $content, $matches) ) {
		$marker_text = sprintf($marker_format, $marker_num);
		$html_tags[$marker_num] = $matches[0];
		$marker_num++;
		$content = str_ireplace( $matches[0], $marker_text, $content );
	}

	if( empty( $html_tags ) )
		return $post_data;

	foreach($html_tags as $tag){
		$tag_lowercase = strtolower( $tag );
		if( substr( $tag_lowercase, 0, 4 ) !== '<img' && substr( $tag_lowercase, 0, 7 ) !== '<iframe' )
			continue;
		if( !strpos( $tag_lowercase, 'http://' ) )
			continue;
		
		$tag_forced_url = str_ireplace( 'http://', 'https://', $tag );
		$original_content = str_ireplace( $tag, $tag_forced_url, $original_content);
	}

	$post_data['post_content'] = wpautop( $original_content );

	return $post_data;
}
add_action( 'wp_insert_post_data', 'force_img_src_https_in_post_content', 1 );
