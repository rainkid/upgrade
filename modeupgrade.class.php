<?php
!defined('P_W') && exit('Forbidden');
ini_set('memory_limit', '256M');
define('DOWNLOAD_DIR', 'upgrade/download/');//���ص�ַ
define('BACKUP_DIR', 'upgrade/backup/');//�����ļ���ַ
/**
 *
 * ģʽ������
 *
 */
class PW_ModeUpgrade{

	var $inspath;
	var $cfgpath;
	var $package;
	var $result;
	/**
	 *
	 * ���ð�װ��Ϣ
	 * @param String $path  ��װ·��
	 * @param String $name  ��Ʒ����
	 */

	function setUpgradeInfo( $config ){
		$this->inspath = S::escapePath( $config['inspath'] );
		$this->cfgpath = S::escapePath( $config['cfgpath'] );
	}

	function setConfig( $package ){
		$insfile = DOWNLOAD_DIR . $package . '/ins_config.php';
		if(!is_file( realpath( $insfile) )) $this->msg( '��װ�����ļ�������!' );
		$config = require realpath($insfile);
		!S::isArray( $config ) && $this->msg( '��װ������Ϣ����ȷ!' );
		$this->setUpgradeInfo( $config );
	}

	/**
	 *
	 * ��ƽ̨��ȡ��Ʒ�б�
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
	 * ��ƽ̨��ȡ��Ʒ���ص�ַ
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
	 * ��ȡƽ̨�ͻ���
	 */
	function getClient(){
		global $db_sitehash, $db_siteownerid;
		L::loadClass('client', 'utility/platformapisdk', false);
		return new PlatformApiClient($db_sitehash, $db_siteownerid);
	}

	/**
	 *
	 * ��ȡ��Ʒ������
	 */
	function getUpgrade(){
		L::loadClass('upgrade','upgrade', false);
		return new PW_Http_Upgrade();
	}

	/**
	 *
	 * ����Ʒ��װĿ¼�Ƿ����
	 */
	function check(){
		if( !$this->inspath ) $this->msg( '��װĿ¼δָ��' );
		return true;
	}

	/**
	 *
	 * ִ�а�װ
	 * @param String $info
	 * @param String $step ��ʾ��Ϣ
	 * @param String $jumpurl ��ת��ַ
	 * @param String $package ��װ��
	 */
	function install( $info, $step, $jumpurl, $package ){
		$this->package = $package;
		$upgrade = $this->getUpgrade();
		switch ($step){
			case 'before':
				$this->result = $upgrade->before( htmlspecialchars_decode(urldecode( $this->package )) );
				if(!$this->result )  adminmsg('����ǰ������');
				break;
			case 'download':
				$this->package = $upgrade->downLoadPackage(htmlspecialchars_decode(urldecode($this->package)));
				if(! $this->package ) adminmsg('�ļ�����ʧ��');
				break;
			case 'unpack':
				$this->package = $upgrade->unPackPackage( $this->package, true );
				if(!$this->package) adminmsg('�ļ���ѹʧ��');
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
	 * ִ�а�װ��Ʒ-���ݿⰲװ
	 * @param unknown_type $name
	 */
	function installProduct(){
		$ins = $this->getInstaller();
		$ins->setInsInfo( $this->inspath ,$this->cfgpath );
		$ins->installProduct();
	}

	/**
	 *
	 * ��ȡ��Ʒ��װ��
	 */
	function getInstaller(){
		return L::loadClass( 'Installer', 'upgrade' );
	}

	/**
	 *
	 * ��ʾ��Ϣ
	 * @param String $info ��Ϣ����
	 * @param String $jumpurl ��ת��ַ
	 */
	function msg( $info, $jumpurl = ''){
		$jumpurl ? adminmsg( $info, $jumpurl ) : adminmsg( $info );
	}

}