<?php
/*
 * based on a similar Python implementation by Xenesis
 * (http://www.infler.de/forum/viewtopic.php?t=6812&sid=205ea83c3dc9e74b1fe987946abf7cf8)
 *
 * Copyright (C) 2010 Clemens Horch <info@clho-it.de>
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses/>.
 */

class MVGLiveConnector {
	private $_objectMap;
	private $_stringMap;
	private $_objectCache;

	private $_options;
	
	public function  __construct() { 
		$this->setOptions();
		date_default_timezone_set('Europe/Berlin');
	}

	public function getLiveData($station, $entries = 4) {
		$stream = $this->downloadData($station, $entries);

		$stream = array_reverse($stream);
		$this->_objectMap = array_reverse(array_slice($stream, 3));
		$this->_stringMap = $stream[2];
		$this->_objectCache = array();

		$data = $this->decode();
		return $data;
	}

	public function setOptions($uBahn = true, $tram = true, $bus = true, $sBahn = true) {
		$this->_options = array(
			'ubahn' => $uBahn,
			'tram'  => $tram,
			'bus'   => $bus,
			'sbahn' => $sBahn,
		);
	}

	private function downloadData($station, $entries) {
		$url = 'http://www.mvg-live.de/MvgLive/mvglive/rpc/guiAnzeigeService';

		$post = "5|0|8|http://www.mvg-live.de/MvgLive/mvglive/|" .
			"0AADC4C63B8E3313F63FB1450997DA6E|" .
			"de.swm.mvglive.gwt.client.departureView.GuiAnzeigeService|".
			"getDisplayAbfahrtinfos|java.lang.String|I|Z|" .
			"$station|1|2|3|4|7|5|6|6|7|7|7|7|8|0|$entries|";

		foreach($this->_options as $value) {
			if ($value) {
				$post .= '1|';
			} else {
				$post .= '0|';
			}
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/x-gwt-rpc; charset=utf-8"));

		$answer = curl_exec($ch);
		curl_close($ch);

		if (substr($answer, 0, 4) <> '//OK') {
			if (strpos($answer, 'de.swm.mvglive.gwt.client.departureView.UnknownStopNameException') !== false) {
				throw new Exception("Unknown station");
			} else {
				throw new Exception("Error downloading data");
			}
		}

		$answer = substr($answer, 4);
		$stream = json_decode($answer);

		return $stream;
	}

	private function decode() {
		$id = array_pop($this->_objectMap);
		
		if ($id > 0) {
			$type =  $this->getType($id);
			
			switch ($type) {
				default:
					throw new Exception("unknown type '$type'");

				case 'de.swm.dfi.net.entity.EinzelAnzeigenInfo':
					return $this->decodeEinzelAnzeigenInfo($id);
					
				case 'java.util.ArrayList':
					return $this->decodeArrayList($id);

				case 'java.util.Date':
					return $this->decodeDate($id);

				case 'java.lang.Integer':
					return $this->decodeInteger($id);

				case 'de.swm.dfi.net.entity.AbfahrtInfo':
					return $this->decodeAbfahrtInfo($id);

				case 'de.swm.dfi.net.entity.FahrtRichtung':
					return $this->decodeFahrtrichtung($id);

				case 'de.swm.dfi.net.entity.Verkehrsmittel':
					return $this->decodeVerkehrsmittel($id);
			}
		} else {
			$cacheIndex = - $id - 2;
			$cacheItem = $this->_objectCache[$cacheIndex];
			$this->pushObject($cacheItem);
			return $cacheItem;
		}
	}

	private function getType($typeId) {
		$tstring = $this->getString($typeId);
		$tstring = explode('/', $tstring);
		return $tstring[0];
	}

	private function getString($stringId) {
		if ($stringId == 0) {
			return '';
		} else {
			return $this->_stringMap[$stringId - 1];
		}
	}

	private function pushObject($obj) {
		$newLen = array_unshift($this->_objectCache, $obj);
	}

	private function decodeEinzelAnzeigenInfo($id) {
		$einzelanzeigeninfo->abfahrten	= $this->decode();
		$einzelanzeigeninfo->date			= $this->decode();
		
		$einzelanzeigeninfo->xxx1			= $this->decode();
		$einzelanzeigeninfo->xxx2			= array_pop($this->_objectMap);

		$this->pushObject($einzelanzeigeninfo);
		return $einzelanzeigeninfo;
	}

	private function decodeArrayList($id) {
		$array = array();
		$length = array_pop($this->_objectMap);

		for ($i = 0; $i < $length; $i++) {
			$array[$i] = $this->decode();
		}

		$this->pushObject($array);
		return $array;
	}

	private function decodeDate($id) {
		$value = array_pop($this->_objectMap) + array_pop($this->_objectMap);
		$value = $value / 1000;
		//$value = date("d.m.Y H:i:s", $value);
		
		$this->pushObject($value);
		return $value;
	}

	private function decodeInteger($id) {
		$value = array_pop($this->_objectMap);
		
		$this->pushObject($value);
		return $value;
	}

	private function decodeAbfahrtInfo($id) {
		$abfahrt->date					= $this->decode();
		$abfahrt->xxx1					= array_pop($this->_objectMap);
		$abfahrt->fahrtrichtung		= $this->decode();
		$abfahrt->imageLine			= $this->getString(array_pop($this->_objectMap));
		$abfahrt->line					= $this->getString(array_pop($this->_objectMap));
		$abfahrt->meansOfTransportSymbol = $this->getString(array_pop($this->_objectMap));
		$abfahrt->via					= $this->getString(array_pop($this->_objectMap));
		$abfahrt->verkehrsmittel	= $this->decode();
		
		$this->pushObject($abfahrt);
		return $abfahrt;
	}

	private function decodeFahrtrichtung($id) {
		$fahrtrichtung->xxx1 = array_pop($this->_objectMap);

		$this->pushObject($fahrtrichtung);
		return $fahrtrichtung;
	}

	private function decodeVerkehrsmittel($id) {
		$verkehrsmittel->type			= array_pop($this->_objectMap);
		$verkehrsmittel->endstation	= $this->getString(array_pop($this->_objectMap));;

		$this->pushObject($verkehrsmittel);
		return $verkehrsmittel;
	}
	
}
