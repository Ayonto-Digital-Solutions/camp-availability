<?php
/**
 * Simple Markdown to HTML Parser
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Markdown_Parser {
	
	public function parse( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}

		$html = $markdown;
		
		// Store code blocks and inline code with placeholders to protect them from formatting
		$code_blocks = array();
		$inline_codes = array();
		
		// Step 1: Extract and protect code blocks FIRST
		$html = preg_replace_callback( '/```([a-z]*)\n(.*?)\n```/s', function( $matches ) use ( &$code_blocks ) {
			$language = $matches[1];
			$code = htmlspecialchars( $matches[2], ENT_QUOTES, 'UTF-8' );
			$placeholder = '___CODE_BLOCK_' . count( $code_blocks ) . '___';
			$code_blocks[ $placeholder ] = '<pre><code class="language-' . $language . '">' . $code . '</code></pre>';
			return $placeholder;
		}, $html );
		
		// Step 2: Extract and protect inline code
		$html = preg_replace_callback( '/`([^`]+)`/', function( $matches ) use ( &$inline_codes ) {
			$code = htmlspecialchars( $matches[1], ENT_QUOTES, 'UTF-8' );
			$placeholder = '___INLINE_CODE_' . count( $inline_codes ) . '___';
			$inline_codes[ $placeholder ] = '<code>' . $code . '</code>';
			return $placeholder;
		}, $html );

		// Step 3: Now process all other markdown (Bold, Italic, Links, etc.)
		// Headers
		$html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

		// Bold
		$html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
		
		// Italic
		$html = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $html );
		
		// Links
		$html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html );
		
		// Lists
		$html = preg_replace_callback( '/^([\-\*] .+)$/m', function( $matches ) {
			return '<li>' . ltrim( $matches[1], '- *' ) . '</li>';
		}, $html );
		
		$html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
		
		// Paragraphs
		$html = '<p>' . preg_replace( '/\n\n/', '</p><p>', $html ) . '</p>';
		
		// Line breaks
		$html = nl2br( $html );
		
		// Step 4: Restore code blocks and inline code (now safe from formatting)
		foreach ( $code_blocks as $placeholder => $code_html ) {
			$html = str_replace( $placeholder, $code_html, $html );
		}
		
		foreach ( $inline_codes as $placeholder => $code_html ) {
			$html = str_replace( $placeholder, $code_html, $html );
		}
		
		return $html;
	}
}
