<?php
!defined('P_W') && exit('Forbidden');
ini_set('memory_limit', '256M');
define('DOWNLOAD_DIR', 'upgrade/download/');//下载地址
define('BACKUP_DIR', 'upgrade/backup/');//备份文件地址
/**
 *
 * 模式更新类
 *
 */
class PW_ModeUpgrade{

	var $inspath;
	var $cfgpath;
	var $package;
	var $result;
	/**
	 *
	 * 设置安装信息
	 * @param String $path  安装路径
	 * @param String $name  产品名称
	 */

	function setUpgradeInfo( $config ){
		$this->inspath = S::escapePath( $config['inspath'] );
		$this->cfgpath = S::escapePath( $config['cfgpath'] );
	}

	function setConfig( $package ){
		$insfile = DOWNLOAD_DIR . $package . '/ins_config.php';
		if(!is_file( realpath( $insfile) )) $this->msg( '安装配置文件不存在!' );
		$config = require realpath($insfile);
		!S::isArray( $config ) && $this->msg( '安装配置信息不正确!' );
		$this->setUpgradeInfo( $config );
	}

	/**
	 *
	 * 从平台获取产品列表
	 * @param unknown_type $params
	 */
	function getProductsList( $params = array() ){
		$client = $this->getClient();
		$result = $client->post( "upgrade.products.getlist", $params );
		$result = PlatformApiClientUtility::decodeJson( $result );
		$result = unserialize($result);
		return PlatformApiClientUtility::convertCharset( 'utf-8', 'gbk', $result);
	}

	/**
	 *
	 * 从平台获取产品下载地址
	 * @param unknown_type $params
	 */
	function getDownUrl( $params ){
		$client = $this->getClient();
		if(! S::isNum( $params['id'] )) return false;
		$result = $client->post("upgrade.products.geturl", $params );
		$result = unserialize($result);
		$result = PlatformApiClientUtility::convertCharset( 'utf-8', 'gbk', $result);
		return urlencode( $result['downurl'] );
	}

	/**
	 *
	 * 获取平台客户端
	 */
	function getClient(){
		global $db_sitehash, $db_siteownerid;
		L::loadClass('client', 'utility/platformapisdk', false);
		return new PlatformApiClient($db_sitehash, $db_siteownerid);
	}

	/**
	 *
	 * 获取产品更新类
	 */
	function getUpgrade(){
		L::loadClass('upgrade','upgrade', false);
		return new PW_Http_Upgrade();
	}

	/**
	 *
	 * 检查产品安装目录是否存在
	 */
	function check(){
		if( !$this->inspath ) $this->msg( '安装目录未指定' );
		return true;
	}

	/**
	 *
	 * 执行安装
	 * @param String $info
	 * @param String $step 提示信息
	 * @param String $jumpurl 跳转地址
	 * @param String $package 安装包
	 */
	function install( $info, $step, $jumpurl, $package ){
		$this->package = $package;
		$upgrade = $this->getUpgrade();
		switch ($step){
			case 'before':
				$this->result = $upgrade->before( htmlspecialchars_decode(urldecode( $this->package )) );
				if(!$this->result )  adminmsg('下载前检查出错');
				break;
			case 'download':
				$this->package = $upgrade->downLoadPackage(htmlspecialchars_decode(urldecode($this->package)));
				if(! $this->package ) adminmsg('文件下载失败');
				break;
			case 'unpack':
				$this->package = $upgrade->unPackPackage( $this->package, true );
				if(!$this->package) adminmsg('文件解压失败');
				break;
			case 'checkfile':
				$this->setConfig( $this->package );
				$this->result  = $upgrade->checkFile( $this->inspath );
				if( is_array( $this->result ) ) return $this->result;
				break;
			case 'backup':
				$this->setConfig( $this->package );
				$upgrade->backUp( $this->inspath, date('Y-m-d H.i.s') );
				break;
			case 'install':
				@chmod('mode',0777);
				$this->setConfig( $this->package );
				$upgrade->installPackage( $this->package );
				$this->installProduct();
				break;
			case 'after':
				$upgrade->after( $this->package );
				@unlink('ins_config.php');
				@chmod('mode',0644);
				break;
			default:
				break;
		}
		$this->msg( $info, $jumpurl .('&package=' . $this->package));
	}

	/**
	 *
	 * 执行安装产品-数据库安装
	 * @param unknown_type $name
	 */
	function installProduct(){
		$ins = $this->getInstaller();
		$ins->setInsInfo( $this->inspath ,$this->cfgpath );
		$ins->installProduct();
	}

	/**
	 *
	 * 获取产品安装类
	 */
	function getInstaller(){
		return L::loadClass( 'Installer', 'upgrade' );
	}

	/**
	 *
	 * 提示信息
	 * @param String $info 信息内容
	 * @param String $jumpurl 跳转地址
	 */
	function msg( $info, $jumpurl = ''){
		$jumpurl ? adminmsg( $info, $jumpurl ) : adminmsg( $info );
	}

}