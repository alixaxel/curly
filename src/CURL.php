<?php

/**
 * The MIT License
 * http://creativecommons.org/licenses/MIT/
 *
 * curly 0.2.0 (github.com/alixaxel/curly/)
 * Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
 */

namespace alixaxel\curly;

class CURL
{
	/**
	 * Create and optionally execute a single cURL request.
	 *
	 * @param string $url request URL
	 * @param array $data request payload
	 * @param string $method HTTP request method
	 * @param bool|string $cookie path to cookie, true for a temporary one
	 * @param array $options additional cURL options to set for the request
	 * @param int $attempts maximum number of retries (0 returns the cURL handle)
	 * @return resource|string|false
	 */
	public static function Uni($url, $data = null, $method = 'GET', $cookie = null, $options = null, $attempts = 3)
	{
		$result = false;

		if (is_resource($curl = curl_init()) === true)
		{
			$default = [
				CURLOPT_AUTOREFERER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_FAILONERROR => true,
				CURLOPT_FORBID_REUSE => true,
				CURLOPT_FRESH_CONNECT => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_TIMEOUT => 30,
			];
			
			if (strpos($url, '#') !== false)
			{
				$url = strstr($url, '#', true);
			}

			$url = rtrim($url, '?&');

			if (preg_match('~^(?:POST|PUT)$~i', $method) > 0)
			{
				if (is_array($data) === true)
				{
					foreach (preg_grep('~^@~', $data) as $key => $value)
					{
						$data[$key] = sprintf('@%s', realpath(ltrim($value, '@')));
					}

					if (count($data) != count($data, COUNT_RECURSIVE))
					{
						$data = http_build_query($data, '', '&');
					}
				}

				$default += [CURLOPT_POSTFIELDS => $data];
			}

			else if ((is_array($data) === true) && (strlen($data = http_build_query($data, '', '&')) > 0))
			{
				$url = sprintf('%s%s%s', $url, (strpos($url, '?') === false) ? '?' : '&', $data);
			}

			if (preg_match('~^(?:HEAD|OPTIONS)$~i', $method) > 0)
			{
				$default += [CURLOPT_HEADER => true, CURLOPT_NOBODY => true];
			}

			$default += [CURLOPT_URL => $url, CURLOPT_CUSTOMREQUEST => strtoupper($method)];

			if (isset($cookie) === true)
			{
				if ($cookie === true)
				{
					$cookie = sprintf('%s.txt', parse_url($url, PHP_URL_HOST));
				}

				if (strcmp('.', dirname($cookie)) === 0)
				{
					$cookie = sprintf('%s/%s', realpath(sys_get_temp_dir()), $cookie);
				}

				$default += array_fill_keys([CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE], $cookie);
			}

			if ((intval(ini_get('safe_mode')) == 0) && (ini_set('open_basedir', null) !== false))
			{
				$default += [CURLOPT_MAXREDIRS => 3, CURLOPT_FOLLOWLOCATION => true];
			}

			curl_setopt_array($curl, (array) $options + $default);

			if (empty($attempts) === true)
			{
				return $curl;
			}

			for ($i = 1; $i <= $attempts; ++$i)
			{
				$result = curl_exec($curl);

				if (($i == $attempts) || ($result !== false))
				{
					break;
				}

				usleep(pow(2, $i - 2) * 1000000);
			}

			curl_close($curl);
		}

		return $result;
	}

	/**
	 * Execute multiple cURL requests in parallel.
	 * 
	 * This method will return an array preserving the original indexes as identification keys.
	 * If $callback is set, it will be called as soon as each request completes, with the following arguments:
	 * 	$callback($body, $info, $key)
	 *
	 * @param array $handles array containing cURL handles
	 * @param null|callable $callback callback to call after each request is done
	 * @param null|int $parallel maximum number of parallel requests
	 * @param null|int $throttle wait at least $throttle seconds for every $parallel requests
	 * @return array|false
	 */
	public static function Multi($handles, $callback = null, $parallel = null, $throttle = null)
	{
		if (is_array($handles) === true)
		{
			$result = [];

			if ((isset($parallel) === true) && (count($handles) > $parallel))
			{
				foreach (array_chunk($handles, max(1, $parallel), true) as $handles)
				{
					$alpha = microtime(true);
					$result = array_merge($result, self::Multi($handles, $callback, null));

					if (isset($throttle) === true)
					{
						if ((($omega = microtime(true)) - $alpha) < $throttle)
						{
							usleep($throttle - ($omega - $alpha) * 1000000);
						}
					}
				}

				return $result;
			}

			if (is_resource($curl = curl_multi_init()) === true)
			{
				$handles = array_filter($handles, function ($handle) use ($curl)
				{
					if ((is_resource($handle) === true) && (strcmp('curl', get_resource_type($handle)) === 0))
					{
						return (curl_multi_add_handle($curl, $handle) === CURLM_OK);
					}

					return false;
				});

				do
				{
					do
					{
						$status = curl_multi_exec($curl, $active);
					}
					while ($status === CURLM_CALL_MULTI_PERFORM);

					while (is_array($value = curl_multi_info_read($curl)) === true)
					{
						if (($key = array_search($handle = $value['handle'], $handles, true)) !== false)
						{
							$result[$key] = false;

							if ($value['result'] === CURLE_OK)
							{
								if ((isset($callback) === true) && (is_callable($callback) === true))
								{
									$result[$key] = call_user_func($callback, curl_multi_getcontent($handle), curl_getinfo($handle), $key);
								}

								else
								{
									$result[$key] = curl_multi_getcontent($handle);
								}
							}

							curl_multi_remove_handle($curl, $handle); curl_close($handle);
						}
					}

					if (($active > 0) && ($status === CURLM_OK) && (curl_multi_select($curl, 1.0) === -1))
					{
						usleep(100);
					}
				}
				while (($active > 0) && ($status === CURLM_OK));

				if ($status !== CURLM_OK)
				{
					foreach ($handles as $handle)
					{
						curl_multi_remove_handle($curl, $handle); curl_close($handle);
					}
				}

				curl_multi_close($curl);
			}

			return $result;
		}

		return false;
	}
	
	/**
	 * Parses HTML into a SimpleXML DOM structure, optionally using an XPath expression.
	 *
	 * @param string|\SimpleXMLElement $html string containing HTML
	 * @param null|string $xpath XPath expression to evaluate
	 * @param null|string|array $key path to return
	 * @param mixed $default default value in case of failure
	 * @return mixed
	 */
	public static function Verse($html, $xpath = null, $key = null, $default = false)
	{
		if (is_string($html) === true)
		{
			$dom = new \DOMDocument();

			if (libxml_use_internal_errors(true) === true)
			{
				libxml_clear_errors();
			}

			$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

			if ((empty($html) !== true) && ($dom->loadHTML($html) === true) && (empty($dom->documentElement) !== true))
			{
				return self::Verse(simplexml_import_dom($dom), $xpath, $key, $default);
			}
		}

		else if (is_object($html) === true)
		{
			if (isset($xpath) === true)
			{
				$html = $html->xpath($xpath);
			}

			if (isset($key) === true)
			{
				if (is_array($key) !== true)
				{
					$key = explode('.', $key);
				}

				foreach ((array) $key as $value)
				{
					$html = (is_object($html) === true) ? get_object_vars($html) : $html;

					if ((is_array($html) !== true) || (array_key_exists($value, $html) !== true))
					{
						return $default;
					}

					$html = $html[$value];
				}
			}

			return $html;
		}

		return $default;
	}
	
	/**
	 * Translates CSS selectors into XPath expressions.
	 *
	 * @param string $selector CSS selector to translate
	 * @return string XPath expression
	 */
	public static function XPathify($selector)
	{
		$regex = [
			'~\s*[>]\s*~' => '>',
			'~\s*[\~]\s*~' => '~',
			'~\s*[+]\s*~' => '+',
			'~\s*[,]\s*~' => ',',
		];
	
		$selector = preg_split('~\s+~', preg_replace(array_keys($regex), $regex, $selector));
	
		foreach ($selector as $key => $value)
		{
			$regex = [
				'~\s*[,]\s*~' => '|descendant-or-self::',
				'~:(button|checkbox|color|date(?:time(?:-local)?)?|email|file|image|month|number|password|radio|range|reset|search|submit|tel|text|time|url|week)~' => 'input[@type="$1"]',
				'~[[](\w+)[]]~' => '*[@$1]',
				'~[[](\w+)=[\'"]?(.*?)[\'"]?[]]~' => '[@$1="$2"]',
				'~^[[]~' => '*[',
				'~([\w-]+)#([\w-]+)~' => '$1[@id="$2"]',
				'~#([\w-]+)~' => '*[@id="$1"]',
				'~([\w-]+)[.]([\w-]+)~' => '$1[contains(concat(" ", @class, " "), " $2 ")]',
				'~[.]([\w-]+)~' => '*[contains(concat(" ", @class, " "), " $1 ")]',
				'~([\w-]+):first-child~' => '*/$1[position() = 1]',
				'~([\w-]+):last-child~' => '*/$1[position() = last()]',
				'~:first-child~' => '*/*[position() = 1]',
				'~:last-child~' => '*/*[position() = last()]',
				'~:nth-last-child[(](\d+)[)]~' => '[position() = (last() - ($1 - 1))]',
				'~([\w-]+):nth-child[(](\d+)[)]~' => '*/*[position() = $2 and self::$1]',
				'~:nth-child[(](\d+)[)]~' => '*/*[position() = $1]',
				'~([\w-]+):contains[(](.*?)[)]~' => '$1[contains(string(.), "$2")]',
				'~\s*[>]\s*~' => '/',
				'~\s*[\~]\s*~' => '/following-sibling::',
				'~\s*[+]\s*([\w-]+)~' => '/following-sibling::$1[position() = 1]',
			];
	
			$selector[$key] = str_replace([']*', ']/*'], ']', preg_replace(array_keys($regex), $regex, $value));
		}
	
		return sprintf('descendant-or-self::%s', implode('/descendant::', $selector));
	}
}
