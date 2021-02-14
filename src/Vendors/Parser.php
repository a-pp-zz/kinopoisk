<?php
namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;

/**
 * JSON-Data Parser
 * @package Kinopoisk/Parser
 * @author CoolSwitcher
 * @version 5.2.2
 */
class Parser extends Kinopoisk {

	const KP_URL_FILM   = 'https://www.kinopoisk.ru/film/%d/';
	const KP_URL_STILLS = 'https://www.kinopoisk.ru/film/%d/stills/';

	protected $_referer = 'https://www.kinopoisk.ru/';
	protected $_content_type = 'html';

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    }

	public function get_data ($cache = false)
	{
		if ($cache AND is_file ($cache) AND $this->_force !== true) {
			$body = file_get_contents($cache);
		} else {
			$url = sprintf (Parser::KP_URL_FILM, $this->_kpid);
			$body = $this->_request ($url);
			if ($cache) {
				file_put_contents($cache, $body);
			}
		}

		$this->_data = $this->_parse_json ($body);
		return ! empty ($this->_data);
	}

	public function get_rating ()
	{
		$this->_rating = $this->_get_rating();
		return ! empty ($this->_rating);
	}

	public function get_frames ($max = 0, $cache = false)
	{
		if ($cache AND is_file ($cache) AND $this->_force !== true) {
			$body = file_get_contents($cache);
		} else {
			$url = sprintf (Parser::KP_URL_STILLS, $this->_kpid);
			$body = $this->_request ($url);
			if ($cache) {
				file_put_contents($cache, $body);
			}
		}

		$this->_frames = $this->_parse_json ($body);

		if ( ! empty ($this->_frames)) {
			$this->_frames = Arr::path ($this->_frames, 'page.images');

			if ($max AND ! empty ($this->_frames)) {
				$this->_frames = array_slice ($this->_frames, 0, $max);
			}
		}

		return ! empty ($this->_frames);
	}

	private function _parse_json ($body)
	{
		$patterns = array (
			'def' => '#\<script type="application\/(ld\+)?json"([\w\s\-]+)?\>(?<json>.*)\<\/script\>#iu',
		);

		foreach ($patterns as $pat_id => $pattern) {
			if (preg_match($pattern, $body, $parts)) {
				$srch = array (
					'@<(\w+)\b.*?>.*?<\/\1>@siu',
					'@<(\w+)\b.*?>@siu',
					'@<\/\w+>@siu'
				);

				$repl = array (
					'',
					'',
					''
				);

				$json = $parts['json'];
				$json = urldecode($json);
				$json = preg_replace($srch, $repl, $json);
				$json = json_decode($json, TRUE);

				if (json_last_error() === JSON_ERROR_NONE) {
					return $json;
				}
			}
		}

		return false;
	}

	protected function _populate ()
	{
		$this->_result = (array)$this->_data;
		$this->_frames = (array)$this->_frames;

		if ( ! empty ($this->_frames)) {
			$this->_result['frames'] = $this->_frames;
		}

		if ( ! empty ($this->_rating)) {
			$this->_result = array_merge ($this->_result, $this->_rating);
		}

		unset ($this->_data);
		unset ($this->_frames);
		unset ($this->_rating);

		$this->_result['kp_id'] = $this->_kpid;

		foreach ($this->_result as $key=>&$value) {

			switch ($key) {
				case 'datePublished':
					$pop_key = 'year';
				break;

				case 'genre':
				case 'countryOfOrigin':
					$value = ( ! empty ($value) AND is_array ($value)) ? implode (', ', $value) : '';
					$pop_key = $key == 'countryOfOrigin' ? 'country' : 'genre';
				break;

				case 'datePublished':
					$pop_key = 'duration';
				break;

				case 'alternateName':
					$pop_key = 'original';
				break;

				case 'timeRequired':
					$value = Kinopoisk::duration_format ($value*60);
					$pop_key = 'duration';
				break;

				case 'actor':
				case 'director':
					$value = ( ! empty ($value) AND is_array ($value)) ? Kinopoisk::array_pluck ($value, 'name') : '';
					$value = ( ! empty ($value) AND is_array ($value)) ? implode (', ', $value) : '';
					$pop_key = $key == 'actor' ? 'actors' : 'director';
				break;

				case 'name':
				case 'description':
				case 'year':
				case 'kp_id':
				case 'rating_kp':
				case 'rating_imdb':
					$pop_key = $key;
				break;

				case 'image':
					$pop_key = 'poster';
					$value = preg_replace ('#^\/\/#', 'https://', $value);
					$size = Kinopoisk::cdn_image_size ($value);

					if (is_array($size) AND count ($size) === 2) {
						list ($width, $height) = $size;
					} else {
						$width = $height = 0;
					}

					$value = array ('image'=>$value, 'width'=>$width, 'height'=>$height);
				break;

				case 'frames':
					$pop_key = 'picshots';
					$value = Kinopoisk::array_pluck ($value, 'baseUrl');

					if ( ! empty ($value)) {
						foreach ($value as &$image) {

							$size = Kinopoisk::cdn_image_size ($image);
							$thumb = $image;

							if (is_array($size) AND count ($size) === 2) {
								list ($width, $height) = $size;
								$thumb = str_replace ($width.'x'.$height, '200x200', $image);
							} else {
								$width = $height = 0;
							}

							$image = array ('image'=>$image, 'thumb'=>$thumb, 'width'=>$width, 'height'=>$height);
						}
					}
				break;

				default:
					$pop_key = null;
				break;
			}

			if ($pop_key) {
				$populated[$pop_key] = $value;
			}
		}

		$this->_result = $populated;
		unset ($populated);
		return true;
	}
}
