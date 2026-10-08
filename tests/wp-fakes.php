<?php
/**
 * Minimal, behaviorally-accurate stand-ins for the WordPress core functions
 * CrumbSignal_Settings relies on, so its sanitization logic can be unit
 * tested without booting WordPress. These are intentionally narrow: they
 * only need to be correct enough to exercise CrumbSignal's own branching,
 * not to be a faithful WordPress core reimplementation.
 *
 * @package CrumbSignal
 */

declare( strict_types=1 );

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = wp_strip_all_tags( (string) $str );
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( wp_strip_all_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color ) {
			return '';
		}
		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$protocols = $protocols ?: array( 'http', 'https' );
		$pattern   = '#^(' . implode( '|', array_map( 'preg_quote', $protocols ) ) . '):/{2}#i';
		if ( ! preg_match( $pattern, $url ) ) {
			return '';
		}
		return preg_replace( '/[^a-z0-9\-~+_.?#=!&;,\/:%@$|*\'()\[\]]/i', '', $url );
	}
}
