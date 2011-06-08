<?php
!defined('P_W') && exit('Forbidden');
class PW_Installer{
	var $sign;
	var $cfgpath;
	var $inspath;
	/**
	 *
	 * ���õ�ǰ��װ��ģʽ
	 * @param String $name ģʽ����
	 */
	function setInsInfo( $inspath, $cfgpath ){
		$this->inspath = $inspath;
		$this->cfgpath = $cfgpath;
		$params = $this->getInfoParams();
		$this->sign = $params['sign'];
	}

	/**
	 *
	 * ��ȡInfo.xml�ļ�����
	 */
	function getModeInfo(){
		!file_exists( $this->inspath ) && adminmsg('ģʽ�ļ��в�����');
		$infofile = $this->inspath . "info.xml";
		!file_exists($infofile) && adminmsg('ģʽInfo�ļ�δ����');
		return (function_exists('file_get_contents')) ? @file_get_contents( $infofile ) : readover( $infofile );
	}

	/**
	 *
	 * ����sql���
	 * @param String $type ����
	 */
	function runSql( $type ){
		require_once(R_P.'require/sql_deal.php');
		$sqlarray = file_exists(R_P . $this->inspath . "sql.txt") ? FileArray($this->sign,'mode') : array();
		!empty($sqlarray) && ($type == 'create' ? SQLCreate($sqlarray, true) : SQLDrop($sqlarray));
	}

	/**
	 *
	 * ����xmlΪ����
	 * @param String $content xml�ļ�����
	 */
	function xml2array($content){
		$temp_array = array();
		if (preg_match('/<modename>(.*?)<\/modename>/i',$content,$match)) $temp_array['modename'] = $match['1'];
		if (preg_match('/<ifpwcache>(\d+)<\/ifpwcache>/i',$content,$match)) $temp_array['ifpwcache'] = $match['1'];
		if (preg_match('/<version>(.*?)<\/version>/i',$content,$match)) $temp_array['version'] = $match['1'];
		if (preg_match('/<sign>(.*?)<\/sign>/i',$content,$match))	$temp_array['sign'] = $match['1'];
		$match_1 = $match_2 = array();
		if (preg_match('/<pages>([^\x00]*?)<\/pages>/i',$content,$match_1)) {
			if(preg_match_all('/<item>[^\x00]*?<name>(.*?)<\/name>[^\x00]*?<template>(.*?)<\/template>[^\x00]*?<scr>(.*?)<\/scr>[^\x00]*?<\/item>/i',$match_1[1],$match_2)) {
				foreach ($match_2[1] as $key => $value) {
					$temp_array['pages']['item'][$key]['name']		= $value;
					$temp_array['pages']['item'][$key]['template']	= $match_2[2][$key];
					$temp_array['pages']['item'][$key]['scr']		= $match_2[3][$key];
				}
			}
		}
		return $temp_array;
	}

	/**
	 *
	 * ��������
	 * @param Array $params ģʽ��ز�������
	 */
	function updateConfig( $params ){
		global $db, $db_modepages, $db_modes;
		if (!$db_modes || !is_array($db_modes)) $db_modes = array();
		$m_name = S::escapeChar($params['modename']);
		$db_modes[$this->sign] = array('m_name' => $m_name,'ifopen'=> 1, 'title'=> $m_name,'version'=> $params['version'], 'sign'=> $params['sign']);
		setConfig('db_modes', $db_modes);
		if ($params['pages']['item']) {
			$items = $params['pages']['item'];
			$pages = array();
			foreach ($items as $value) {
				!$value['scr'] && $value['scr'] = 'public';
				$pages[$value['scr']] = array('name' => $value['name'], 'template' => $value['template']);
			}
			$db_modepages[$this->sign] = ($pages) ? $pages : "";
			setConfig('db_modepages', $db_modepages);
		}

		if ((int) $params['ifpwcache']) {
			$params['ifpwcache'] = (int)$params['ifpwcache'];
			$rt = $db->get_one("SELECT db_name FROM pw_config WHERE db_name='db_ifpwcache'");
			if (!empty($rt)) {
				$db->update("UPDATE pw_config SET db_value=db_value|".$params['ifpwcache'].",vtype='string' WHERE db_name='db_ifpwcache'");
			} else {
				$db->update("INSERT INTO pw_config SET db_name='db_ifpwcache',vtype='string',db_value=".S::sqlEscape($params['ifpwcache']));
			}
		}
	}

	function getInfoParams(){
		$filedata = $this->getModeInfo();
		$this->runSql('create');
		return $this->xml2array( $filedata );
	}

	/**
	 *
	 * ��װ��Ʒ
	 */
	function installProduct(){
		global $db_modes, $db;
		if ($this->sign && !array_key_exists( $this->sign, $db_modes)) {
			$params = $this->getInfoParams();
			$this->updateConfig( $params );
			$this->runSql('create');
			$this->updateNav( $params );
		}
		updatecache_c();
		updatecache_conf($this->sign);
		$installfile = S::escapePath($this->cfgpath .'install.php');
		include $installfile;
	}

	/**
	 *
	 * ж�ز�Ʒ
	 */
	function uninstallProduct(){
		global $db_modes, $db_modepages;
		!array_key_exists($this->sign,$db_modes) && adminmsg('ģʽδ����');
		$this->runSql('drop');
		$this->updateCache();
		closedir($fp);
		$pw_cachedata = L::loadDB('cachedata', 'area');
		$pw_cachedata->truncate();
		unset($db_modes[$this->sign]);
		setConfig('db_modes', $db_modes);
		unset($db_modepages[$this->sign]);
		setConfig('db_modepages', $db_modepages);
		if ($this->sign == $db_mode) setConfig('db_mode', 'bbs');
		$navConfigService = L::loadClass('navconfig', 'site');
		$navConfigService->deleteByKey($this->sign);
		updatecache_c();
		$uninstallfile = S::escapePath(R_P. $this->cfgpath .'uninstall.php');
		is_file($uninstallfile) && require_once ($uninstallfile);
	}

	/**
	 *
	 * ���»���
	 */
	function updateCache(){
		$fp = opendir(D_P.'data/tplcache/');
		while ($filename = readdir($fp)) {
			if($filename=='..' || $filename=='.' || strpos($filename,'.htm')===false) continue;
			P_unlink(S::escapePath(D_P.'data/tplcache/'.$filename));
		}
		closedir($fp);
	}

	/**
	 *
	 * ���µ���
	 * @param String $params ģʽ����
	 */
	function updateNav( $params ){
		global $db_modedomain;
		$m_name = S::escapeChar($params['modename']);
		$navlists = array(
			'nkey'	=> $this->sign,
			'type'	=> 'main',
			'pos'	=> '-1',
			'title'	=> strip_tags($m_name),
			'style'	=> '',
			'link'	=> ($db_modedomain[$this->sign] ? $db_modedomain[$this->sign] : 'index.php?m='.$this->sign),
			'alt'	=> '',
			'target'=> $target,
			'view'	=> 0,
			'upid'	=> 0,
			'isshow'=> 1
		);
		$navConfigService = L::loadClass('navconfig', 'site');
		$exist = $navConfigService->getByKey($this->sign, PW_NAV_TYPE_MAIN);
		$exist ? $navConfigService->update($exist['nid'], $navlists) : $navConfigService->add(PW_NAV_TYPE_MAIN, $navlists);
	}

	/**
	 *
	 * ��ȡ�Ѿ���װ��Ʒ�б�
	 */
	function getInstalls(){
		return $db_modes;
	}
}