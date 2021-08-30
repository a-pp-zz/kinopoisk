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
	const UA_DATA = 'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25';
	const UA_DEFAULT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_0 like Mac OS X) AppleWebKit/602.1.38 (KHTML, like Gecko) Version/10.0 Mobile/14A5297c Safari/602.1';


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
			$this->_agent = Parser::UA_DATA;
			$body = $this->_request ($url);
			if ($cache) {
				file_put_contents($cache, $body);
			}
		}

		$this->_data = $this->_parse_json ($body, 'data');
		return ! empty ($this->_data);
	}

	public function get_rating ()
	{
		$this->_agent = Parser::UA_DEFAULT;
		$this->_rating = $this->_get_rating();
		return ! empty ($this->_rating);
	}

	public function get_frames ($max = 0, $cache = false)
	{
		if ($cache AND is_file ($cache) AND $this->_force !== true) {
			$body = file_get_contents($cache);
		} else {
			$url = sprintf (Parser::KP_URL_STILLS, $this->_kpid);
			$this->_agent = Parser::UA_DEFAULT;
			$body = $this->_request ($url);
			if ($cache) {
				file_put_contents($cache, $body);
			}
		}

		$this->_frames = $this->_parse_json ($body, 'frames');

		if ( ! empty ($this->_frames)) {
			$this->_frames = Arr::path ($this->_frames, 'page.images');

			if ($max AND ! empty ($this->_frames)) {
				$this->_frames = array_slice ($this->_frames, 0, $max);
			}
		}

		return ! empty ($this->_frames);
	}

    public function get_staff ($max = 0, $cache = false)
    {
        return false;
    }

	private function _parse_json ($body, $type = 'data')
	{
		$domd = new \DOMDocument;
		libxml_use_internal_errors(true);
		$domd->loadHTML($body);
		libxml_use_internal_errors(false);
		$domd->encoding = 'UTF-8';

		if ($type == 'frames') {
			$items = $domd->getElementsByTagName('script');
			$data = array();
			$json = false;

			foreach($items as $item) {
				$outer_html = $this->_utf_decode ($domd->saveHTML($item));
				$inner_html = $this->_utf_decode ($domd->saveHTML($item->firstChild));
				$outer_html = urldecode ($outer_html);
				$inner_html = urldecode ($inner_html);

				if (strpos($outer_html, 'application/json') !== false) {
					$data[] = $inner_html;
				}
			}

			if ( ! empty ($data)) {
				$json = array_pop ($data);
			}
		} else {
			$items = $domd->getElementById('__next');
			$item = ! empty ($items->firstChild) ? $items->firstChild : false;

			if ( ! empty ($item)) {
				$json = $this->_utf_decode ($domd->saveHTML($item->firstChild));
				$json = urldecode ($json);
			} else {
				return false;
			}

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

			$json = preg_replace ($srch, $repl, $json);
		}

		$json = json_decode ($json, TRUE);

		if (json_last_error() === JSON_ERROR_NONE) {

			if ($type == 'data' AND empty ($json['actor']) AND empty ($json['director'])) {
				$next_data = $this->_parse_next_data ($body);

				if ( ! empty ($next_data)) {
					$json = array_merge ($json, $next_data);
				}
			}

			return $json;
		}

		return false;
	}

	private function _parse_next_data ($body)
	{
		$domd = new \DOMDocument;
		libxml_use_internal_errors(true);
		$domd->loadHTML($body);
		libxml_use_internal_errors(false);
		$domd->encoding = 'UTF-8';

		$json = false;
		$data = array ();
		$items = $domd->getElementById('__NEXT_DATA__');
		$item = ! empty ($items->firstChild) ? $items->firstChild : false;

		if ( ! empty ($item)) {
			$json = $this->_utf_decode ($domd->saveHTML($item));
			$json = urldecode ($json);
		} else {
			return $json;
		}

		$json = json_decode ($json, TRUE);

		if (json_last_error() === JSON_ERROR_NONE) {
			$persons = $data = array ();
			$film = (array)Arr::path ($json, 'props.apolloState.data');

			foreach ($film as $key => $values) {
				if (preg_match ('#Person\:\d+#iu', $key)) {
					$id = Arr::get ($values, 'id');
					$name = Arr::get ($values, 'name');
					$originalName = Arr::get ($values, 'originalName');
					$persons[$id] = ! empty ($name) ? $name : $originalName;
				}
				elseif (preg_match ('#Film\:\d+#iu', $key)) {
					foreach ($values as $film_key => $film_data) {
						if (stripos ($film_key, 'members(') !== false) {
							if (stripos ($film_key, 'DIRECTOR') !== false) {
								$subkey = 'director';
							} elseif (stripos ($film_key, 'ACTOR') !== false) {
								$subkey = 'actor';
							} else {
								continue;
							}

							foreach ((array)Arr::get ($film_data, 'items') as $item) {
								$person_id = Arr::path ($item, 'person.__ref');
								$person_id = str_replace ('Person:', '', $person_id);

								if ($person_id) {
									$person = Arr::get ($persons, $person_id);
									if ($person) {
										$data[$subkey][] = array ('name'=>$person, 'url'=>'/name/'.$person_id);
									}
								}
							}
						}
					}
				}
			}
		}

		unset ($persons);
		return $data;
	}

	private function _utf_decode ($text)
	{
		if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
			return $text;
		}

		return utf8_decode ($text);
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
		$populated = array ();

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
