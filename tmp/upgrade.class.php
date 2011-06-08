<?php
!defined('P_W') && exit('Forbidden');
Class PW_Upgrade{

	/**
	 *
	 * ��Ϣ���
	 * @param String $str
	 */
	function msg($str){
		showmsg($str);
	}

	function before( $package ){}
	function downLoadPackage( $package ){}
	/**
	 *
	 * ��ѹѹ����
	 * @param string $package
	 * @param string $deletePackage ��ѹ��ɺ��Ƿ�ɾ��ѹ����
	 */
	function unPackPackage( $package , $deletePackage = true){}
	/**
	 *
	 * �Ƚ��ļ��汾
	 * @param string $package
	 */
	function checkFile( $package ){}
	/**
	 *
	 * �����ļ�
	 * @param String $tmpdir �ļ�Ŀ¼
	 * @param String $packageName ѹ��������
	 */
	function backUp( $package, $packageName = '' ){}
	/**
	 *
	 * ִ�а�װ��
	 * @param string $package
	 */
	function installPackage( $package , $dir){}

	/**
	 *
	 * ���߰�װ����
	 * @param unknown_type $package
	 */
	function after( $package ){}

	/**
	 *
	 * ��ȡ�ļ�������
	 */
	function getFileSys(){
		include_once 'filesystem.class.php';;
		static $fileSys;
		if( !$fileSys ) $fileSys = new PW_FileSystem();
		return $fileSys;
	}

	/**
	 *
	 * ��ȡhttpʵ��
	 */
	function getHttp(){
		return L::loadClass('http', 'upgrade');
	}
}

Class PW_Http_Upgrade Extends Pw_Upgrade{

	/**
	 * ����ǰ�ļ�Ȩ���Լ�Ŀ¼�ɲ����Լ��
	 * @see PW_Upgrade::_preAction()
	 */
	function before( $package ){
		if ( ! preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package) )	$this->msg('ֻ֧��Զ������');//֧��Զ�����أ�ftp����
		if ( empty($package) ) $this->msg("���ݰ�������");
		if ( !defined('DOWNLOAD_DIR') || !defined('BACKUP_DIR')) $this->msg("�����ļ�Ŀ¼δ����");
		if ( !is_dir(DOWNLOAD_DIR) ) $this->getFileSys()->smkdir( DOWNLOAD_DIR );
		if ( !file_exists(DOWNLOAD_DIR)){
			if( !$this->getFileSys()->smkdir( DOWNLOAD_DIR ) ) $this->msg('����Ŀ¼������');
		}
		if( !is_dir(BACKUP_DIR) ) $this->getFileSys()->smkdir( BACKUP_DIR );
		if ( !file_exists(BACKUP_DIR)){
			if( !$this->getFileSys()->smkdir( BACKUP_DIR ) ) $this->msg('����Ŀ¼������');
		}
		return true;
	}

	/**
	 * �����ļ�
	 * @see PW_Upgrade::downLoadPackage()
	 */
	function downLoadPackage( $package, $args =array() ){
		set_time_limit(500);
		return $this->_downLoadUrl( $package, $args);
	}

	/**
	 * ��ѹ�ļ�
	 * @see PW_Upgrade::unPackPackage()
	 */
	function unPackPackage( $package , $deletePackage = true ){
		set_time_limit(500);
		$filesys = $this->getFileSys();
		$tmpdir = DOWNLOAD_DIR . basename($package, '.tmp');
		$package = DOWNLOAD_DIR . $package;
		$result = $filesys->unzipFile($package, $tmpdir);
		if ( $deletePackage ) unlink($package);
		if ( !$result ) $this->msg('��ѹ���ļ�ʧ��');
		return basename($tmpdir);
	}

	/**
	 * (non-PHPdoc)
	 * @see PW_Upgrade::checkFile()
	 */
	function checkFile( $package ){
		if(! is_dir( $package )) return true; 
		$md5file = trim(( $package . 'safe.md5' ));
		if( !is_file( $md5file ) ) $this->msg('�ļ����md5�ļ���ʧ');
		$olds = include S::escapePath( $md5file );
		$filesys = $this->getFileSys();
		$news = array();
		$filesys->safefile( &$news, $package, '\.js|\.php|\.htm', 1);
		
		$result = array();
		foreach($olds as $file=>$md5key){
			$file = trim($file);
			if(!isset($news[$file])){
				$result[$file] = 'losted';
			} elseif($md5key != $news[$file]){
				$result[$file] = 'modified';
			}
		}
		return $result ? $result : true; 
	}

	/**
	 *�����ļ�|���ݿ�
	 * @see PW_Upgrade::backUp( $tmpdir, $packageName = '' )
	 */
	function backUp( $package, $packageName = '' ){
		if( !is_dir( $package) ) return true;
		set_time_limit(500);
		!$packageName && $packageName = uniqid(time());
		$fileSys = $this->getFileSys();
		$ret = $fileSys->zipFile($package, BACKUP_DIR . $packageName, 'back');
		if(! $ret ) $this->msg('�ļ�����ʧ��');
		return true;
	}

	/**
	 * ��װ���ݿ��Xcopy�ļ�
	 * @see PW_Upgrade::installPackage()
	 */
	function installPackage($package, $dir = './'){
		set_time_limit(500);
		$package = DOWNLOAD_DIR . $package;
		$fileSys = $this->getFileSys();
		$ret = $fileSys->scopy($package, realpath(R_P) );
		!$ret && $this->msg('��װʧ��');
		return true;
	}

	/**
	 * (non-PHPdoc) ��װ���ڴ���
	 * @see PW_Upgrade::after()
	 */
	function after($package){
		$package = DOWNLOAD_DIR . $package;
		$this->getFileSys()->srmdir( $package );
		return true;
	}
	
	/**
	 *
	 * ͨ��Url�����ļ�
	 * @param string $url
	 */
	function _downLoadUrl($url, $args){
		!S::isArray($args) && $args = array();
		$args = array_merge(array('timeout' => 300), $args);
		$http = $this->getHttp();
		!$http && $this->msg('Httpʵ����ʧ��');
		if ( ! $url ) $this->msg('���ص�ַ����Ϊ��');
		$tmpfname = $this->tempnam();
		if ( ! $tmpfname ) $this->msg('���ػ����ļ�����ʧ��:' . $tmpfname);
		$handle = @fopen($tmpfname, 'wb');
		if ( ! $handle ) $this->msg('�ļ���ʧ��:'.$tmpfname);
		$response = $http->request($url, $args);
		if ( !($response) ) {
			fclose($handle); unlink($tmpfname);	return $response;
		}
		if ( $response['response']['code'] != '200' ){
			fclose($handle); unlink($tmpfname);	$this->msg('Զ������ʧ�ܣ�'.trim($response['response']['message']));
		}
		fwrite($handle, $response['body']);
		fclose($handle);
		return basename($tmpfname);
	}

	/**
	 *
	 * ��������ʱ�Ļ����ļ�
	 * @param String $filename �����ļ�����
	 * @param String $dir �����ļ�Ŀ¼
	 */
	function tempnam($filename = '', $dir = ''){
		if(!$dir) $dir = $this->getTempDir();
		!$dir && $this->msg('����Ŀ¼����ʧ��');
		$filename = basename($filename);
		if ( empty($filename) ) $filename = time().'.tmp';
		$filename = preg_replace('|\..*$|', '.tmp', $filename);
		$filename = $dir .'/'. $filename;
		$this->getFileSys()->touchFile($filename);
		if(! @is_writable($filename) ) $this->msg('�ļ�����д:'.$filename);
		return $filename;
	}

	/**
	 *
	 * ��ȡ�����ļ�Ŀ¼
	 */
	function getTempDir(){
		static $temp;
		if ( $temp ) return $temp;
		$temp = DOWNLOAD_DIR;
		if ( !file_exists($temp) ){
			if(!$this->getFileSys()->smkdir( $temp )) return false;
		}
		return $temp;
	}

}
