<?php
/*
Plugin Name: Gisted
Plugin URI: http://jeffsebring.com/wordpress/plugins/gisted
Description: Print your Github Gists as HTML on your WordPress blog
Author: Jeff Sebring
Version: 0.1.2
Author URI: http://jeffsebring.com/

Copyright 2012 Jeff Sebring

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


if ( ! class_exists( 'rGisted' ) )  :

class rGisted
{

	private $raw = null;
	private $clean = null;

	public function __construct()
	{

		add_action( 'wp_enqueue_scripts', array( $this, 'css' ) );
		add_shortcode( 'gisted', array( $this, 'shortcode' ) );

	}

	public function shortcode( $atts )
	{

		extract( shortcode_atts( array(
			'repo' => null,
			'file' => false,
			'file_info' => false,
			'header' => false,
		), $atts ) );

		return $this->_html( $repo, $file, $file_info, $header );

	}

	public function css()
	{

		wp_enqueue_style( 'gisted', plugins_url( 'css/gisted.css', __FILE__ ) );

	}

	public function parser()
	{

		wp_enqueue_script( 'json2' );

	}

	private function _gist( $repo )
	{

		if ( ! $repo )
		return;

		$transient = 'gisted_repo_' . $repo;
		$this->clean = get_transient( $transient );

		if ( ! $this->clean ) :

			add_action( 'wp_print_scripts', array( $this, 'parser' ) );

			$this->raw  = wp_remote_get( 'https://api.github.com/gists/' . $repo );
			$this->raw  = json_decode( maybe_unserialize( $this->raw[ 'body' ] ) );

			if ( isset( $this->raw->description ) )
				$this->clean[ 'description' ] = esc_attr( $this->raw->description );

			if ( isset( $this->raw->html_url ) )
				$this->clean[ 'html_url' ] = esc_attr( $this->raw->html_url );

			if ( isset( $this->raw->user->login ) )
				$this->clean[ 'user' ][ 'login' ] = esc_attr( $this->raw->user->login );

			if ( isset( $this->raw->user->avatar_url ) )
				$this->clean[ 'user' ][ 'avatar_url' ] = esc_attr( $this->raw->user->avatar_url );

			if ( ! empty( $file ) && isset( $this->raw->files->$file ) )
				$this->clean[ 'files' ][ 'file' ] = esc_attr( $this->raw->files->$file ); 

			if ( ! empty( $this->raw->files ) )
				foreach ( $this->raw->files as $file => $data )
					$this->clean[ 'files' ][ $file ][ 'content' ] = esc_html( $data->content );

			set_transient( $transient, $this->clean, 60 * 5 );

		endif;

		return $this->clean;

	}

	private function _html( $repo = null, $file = null, $file_info = false, $header = null )
	{

		if ( ! $repo ) return;

		$gist = $this->_gist( $repo ); 

		if ( empty( $gist[ 'files' ] ) ) return;

		
		$out = '<div class="gisted">';

		if ( $header == true )  :

			$out .= '<header class="gist-header">' . PHP_EOL;
			$out .= '<div class="gist-info">' . PHP_EOL;
			$out .= '<h4>' . $gist[ 'description' ] . '</h4>' . PHP_EOL;
			$out .= '<span class="gist-link">by ' . PHP_EOL;
			$out .= '<a target="_blank" class="gist-author-link" href="http://github.com/' . $gist[ 'user' ][ 'login' ] . '">' . PHP_EOL;
			$out .= $gist[ 'user' ][ 'login' ];
			$out .= '</a>' . PHP_EOL;
			$out .= '</span>' . PHP_EOL;
			$out .= '<span class="gist-link">Fork it on ' . PHP_EOL;
			$out .= '<a target="_blank" href="' . $gist[ 'html_url' ] . '">' . PHP_EOL;
			$out .= 'Github' . PHP_EOL;
			$out .= '</a>' . PHP_EOL;
			$out .= '</span>' . PHP_EOL;
			$out .= '</div>' . PHP_EOL;
			$out .= '<img class="gist-author-avatar" src="' . $gist[ 'user' ][ 'avatar_url' ] . '" />' . PHP_EOL;
			$out .= '</header>' . PHP_EOL;

		endif;

		$out .= '<section class="gist-files">' . PHP_EOL;

		if ( $file )   :

		if ( empty( $gist[ 'files' ][ $file ] ) ) return;

			$data = $gist[ 'files' ][ $file ]; 

			if ( $file_info )   :

				$out .= '<span class="gisted-file" id="' . $file . '">' . PHP_EOL;
				$out .= '<h5>' .  $gist[ 'description' ] . '</h5> &rArr;' . PHP_EOL; 
				$out .= '<a target="_blank" href="' . $gist[ 'html_url' ] . '#' . $file . '">' . PHP_EOL; 
				$out .= $file;
				$out .= '</a>' . PHP_EOL; 
				$out .= '</span>' . PHP_EOL;

			endif;

			$out .= '<pre class="gisted-file-contents">' . $data[ 'content' ] . '</pre>' . PHP_EOL;

			else  :

			if ( ! empty( $gist[ 'files' ] ) )   :

				foreach ( $gist[ 'files' ] as $file => $data )  :

					$out .= '<span class="gisted-file" id="' . $file . '">' . PHP_EOL;
					$out .= '<h5>' . $gist[ 'description' ] . '</h5> &rArr;' . PHP_EOL;
					$out .= '<a target="_blank" href="' . $gist[ 'html_url' ] . '#' . $file . '">' . PHP_EOL;
					$out .= $file . PHP_EOL;
					$out .= '</a>' . PHP_EOL;
					$out .= '</span>' . PHP_EOL;
					$out .= '<pre class="gisted-file-contents">' . $data[ 'content' ] . '</pre>' . PHP_EOL;

				endforeach;

			endif;

		endif;

		$out .= '</section>' . PHP_EOL;
		$out .= '</div>' . PHP_EOL;
		
		return $out;

	}

}

new rGisted;

endif;
