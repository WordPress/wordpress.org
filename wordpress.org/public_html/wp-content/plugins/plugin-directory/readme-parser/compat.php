<?php

if ( !defined('WORDPRESS_README_MARKDOWN') ) {
	define('WORDPRESS_README_MARKDOWN', dirname(__FILE__) . '/markdown.php');
}

require_once(dirname(__FILE__) . '/ReadmeParser.php');

class _WordPress_org_Readme extends Baikonur_ReadmeParser {
	public static function parse_readme($file) {
		$contents = file($file);
		return self::parse_readme_contents($contents);
	}

	public static function parse_readme_contents($contents) {
		if (empty($contents)) {
			return array();
		}

		$result = parent::parse_readme_contents($contents);
		foreach ($result->sections as &$section) {
			$section = self::filter_text($section);
		}
		if (!empty($result->upgrade_notice)) {
			foreach ($result->upgrade_notice as &$notice) {
				$notice = self::sanitize_text($notice);
			}
		}
		if (!empty($result->screenshots)) {
			foreach ($result->screenshots as &$shot) {
				$shot = self::filter_text($shot);
			}
		}

		if (!empty($result->remaining_content)) {
			$result->remaining_content = implode("\n", $result->remaining_content);
			$result->remaining_content = self::filter_text(str_replace("</h3>\n\n", "</h3>\n", $result->remaining_content));
		}
		else {
			$result->remaining_content = '';
		}

		$result->name = self::sanitize_text($result->name);
		//$result->short_description = self::sanitize_text($result->short_description);
		$result->donate_link = esc_url($result->donate_link);

		$result->requires_at_least = $result->requires;
		$result->tested_up_to = $result->tested;
		unset($result->requires, $result->tested);
		$result = ((array) $result);
		return $result;
	}

	protected static function trim_short_desc(&$desc) {
		$desc = self::sanitize_text($desc);
		return parent::trim_short_desc($desc);
	}

	protected static function sanitize_text( $text ) { // not fancy
		$text = strip_tags($text);
		$text = esc_html($text);
		$text = trim($text);
		return $text;
	}

	protected static function filter_text( $text ) {
		$text = trim($text);
		//$text = self::code_trick($text); // A better parser than Markdown's for: backticks -> CODE

		$allowed = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'rel' => array()),
			'blockquote' => array('cite' => array()),
			'br' => array(),
			'p' => array(),
			'code' => array(),
			'pre' => array(),
			'em' => array(),
			'strong' => array(),
			'ul' => array(),
			'ol' => array(),
			'li' => array(),
			'h3' => array(),
			'h4' => array()
		);

		$text = balanceTags($text);

		$text = wp_kses( $text, $allowed );
		$text = trim($text);
		return $text;
	}
}