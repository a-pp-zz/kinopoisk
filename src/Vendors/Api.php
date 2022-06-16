<?php
/**
 * Unofficial API Wrapper
 * @link https://kinopoiskapiunofficial.tech
 * @package Kinopoisk/Api
 * @author CoolSwitcher
 * @version 1.2
 */

namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Http\CurlClient;
use \AppZz\Http\Helpers\FastImage;

class Api extends Kinopoisk {

	const API_HOST            = 'https://kinopoiskapiunofficial.tech';
	const API_FILMS_ENDPOINT  = '/api/v2.2/films/%d';
	const API_FRAMES_ENDPOINT = '/api/v2.2/films/%d/images';
	const API_STAFF_ENDPOINT  = '/api/v1/staff';

	protected $_referer = '';
	protected $_content_type = 'json';
	protected $_staff;

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    }

    public function version ($version = '2.1')
    {
    	return $this;
    }

    public function api_key ($key = '')
    {
    	$this->_headers = array (
    		'X-API-KEY' => $key
    	);
    	return $this;
    }

	public function get_data ($cache = false)
	{
		$url = Api::API_HOST.sprintf (Api::API_FILMS_ENDPOINT, $this->_kpid);
		$this->_data = $this->_request ($url);

		if (is_object($this->_data)) {
			$this->_data = (array)json_decode(json_encode($this->_data), true);
			/*
			$poster = $this->_get_images('POSTER', 1, 1);

			if ( ! empty ($poster)) {
				$this->_data['posterUrl'] = Arr::path($poster, '0.imageUrl');
			}
			*/
		}

		return ! empty ($this->_data);
	}

	public function get_frames ($max = 5, $cache = false)
	{
		$this->_frames = $this->_get_images('STILL', $max, 1);
		return ! empty ($this->_frames);
	}

	public function get_posters ($max = 5)
	{
		return $this->_get_images('POSTER', $max, 1);
	}

	public function get_staff ($max = 10, $cache = false)
	{
		$url = Api::API_HOST.Api::API_STAFF_ENDPOINT.'?'.http_build_query (array('filmId'=>$this->_kpid));
		$staff = $this->_request ($url);

		if ($staff) {
			$this->_staff = array ();
			$staff = (array)json_decode(json_encode($staff), true);

			foreach ($staff as $values) {
				$key  = mb_strtolower(Arr::get ($values, 'professionKey'));
				$name = Arr::get ($values, 'nameRu');

				if (in_array ($key, array ('director', 'actor'))) {
					$this->_staff[$key][] = $name;
				}
			}

			unset ($staff);
			unset ($values);

			if ( ! empty ($this->_staff)) {
				foreach ($this->_staff as &$values) {
					$values = array_slice ($values, 0, $max);
				}
			}
		}

		return ! empty ($this->_staff);
	}

	public function get_rating ()
	{
		$this->_rating = $this->_get_rating();
		return ! empty ($this->_rating);
	}

	private function _implode_arrays ($array = array (), $sep = ', ')
	{
		foreach ($array as $key=>&$value) {
			if (is_array($value)) {
				$value = implode ($sep, $value);
			}
		}

		return implode ($sep, $array);
	}

	private function _clean_picshot_url ($url)
	{
		if (preg_match ('#(https\:\/\/avatars\.mds\.yandex\.net)(.*)#iu', $url, $parts)) {
			$url = $parts[1].$parts[2];
		}

		return $url;
	}

	protected function _get_images ($type = 'STILL', $max = 5, $page = 1)
	{
		$ret = array ();
		$url = Api::API_HOST.sprintf (Api::API_FRAMES_ENDPOINT, $this->_kpid).'?'.http_build_query (array('type'=>$type, 'page'=>$page));
		$images = $this->_request ($url);

		if (is_object($images)) {
			$images = (array)json_decode(json_encode($images), true);
			$total = Arr::get($images, 'total', 0);
			$items = (array)Arr::get($images, 'items');

			if ($total > 0 AND ! empty ($items)) {
				$ret = array_slice ($items, 0, $max);
			}
		}

		return $ret;
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

		if ( ! empty ($this->_staff)) {
			$this->_result = array_merge ($this->_result, $this->_staff);
		}

		unset ($this->_data);
		unset ($this->_frames);
		unset ($this->_rating);
		unset ($this->_staff);

		$populated = array ();

		foreach ($this->_result as $key=>&$value) {

			switch ($key) {
				case 'kinopoiskId':
					$pop_key = 'kp_id';
				break;
				case 'countries':
					$value = $this->_implode_arrays($value, ', ');
					$pop_key = 'country';
				break;
				case 'genres':
					$value = $this->_implode_arrays($value, ', ');
					$pop_key = 'genre';
				break;

				case 'filmLength':
					$pop_key = 'duration';
					$value = Kinopoisk::duration_format ($value*60);
				break;

				case 'nameRu':
					$pop_key = 'name';
					$value = ! empty ($value) ? $value : '';
				break;

				case 'nameOriginal':
					$pop_key = 'original';
					$value = ! empty ($value) ? $value : '';
				break;

				case 'description':
				case 'year':
					$pop_key = $key;
				break;

				case 'ratingKinopoisk':
					$pop_key = 'rating_kp';
				break;

				case 'ratingImdb':
					$pop_key = 'rating_imdb';
				break;

				case 'director':
				case 'actor':
					$pop_key = $key == 'actor' ? $key.'s' : $key;
					$value = ( ! empty ($value) AND is_array ($value)) ? implode (', ', $value) : '';
				break;

				case 'posterUrl':
					$pop_key = 'poster';
					//$value = $this->_clean_picshot_url ($value);
					$fi = new FastImage ($value);
					$size = $fi->get_size ();

					if (is_array($size) AND count ($size) === 2) {
						list ($width, $height) = $size;
					} else {
						$width = $height = 0;
					}

					$value = array ('image'=>$value, 'width'=>$width, 'height'=>$height);
					unset($fi);
				break;

				case 'frames':
					$pop_key = 'picshots';
					foreach ($value as &$image) {
						$image_url = Arr::get ($image, 'imageUrl');
						$preview_url = Arr::get ($image, 'previewUrl');

						//$image_url = $this->_clean_picshot_url ($image_url);
						//$preview_url = $this->_clean_picshot_url ($preview_url);

						if ( ! empty ($image_url)) {
							$fi = new FastImage ($image_url);
							$size = $fi->get_size ();

							if (is_array($size) AND count ($size) === 2) {
								list ($width, $height) = $size;
							} else {
								$width = $height = 0;
							}

							$image = array ('image'=>$image_url, 'thumb'=>$preview_url, 'width'=>$width, 'height'=>$height);
							unset($fi);
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
	}
}
