<?php
!defined('P_W') && exit('Forbidden');
Class PW_Upgrade{

	/**
	 *
	 * 信息输出
	 * @param String $str
	 */
	function msg($str){
		showmsg($str);
	}

	function before( $package ){}
	function downLoadPackage( $package ){}
	/**
	 *
	 * 解压压缩包
	 * @param string $package
	 * @param string $deletePackage 解压完成后是否删除压缩包
	 */
	function unPackPackage( $package , $deletePackage = true){}
	/**
	 *
	 * 比较文件版本
	 * @param string $package
	 */
	function checkFile( $package ){}
	/**
	 *
	 * 备份文件
	 * @param String $tmpdir 文件目录
	 * @param String $packageName 压缩包名称
	 */
	function backUp( $package, $packageName = '' ){}
	/**
	 *
	 * 执行安装包
	 * @param string $package
	 */
	function installPackage( $package , $dir){}

	/**
	 *
	 * 在线安装后处理
	 * @param unknown_type $package
	 */
	function after( $package ){}

	/**
	 *
	 * 获取文件操作类
	 */
	function getFileSys(){
		include_once 'filesystem.class.php';;
		static $fileSys;
		if( !$fileSys ) $fileSys = new PW_FileSystem();
		return $fileSys;
	}

	/**
	 *
	 * 获取http实例
	 */
	function getHttp(){
		return L::loadClass('http', 'upgrade');
	}
}

Class PW_Http_Upgrade Extends Pw_Upgrade{

	/**
	 * 下载前文件权限以及目录可操作性检查
	 * @see PW_Upgrade::_preAction()
	 */
	function before( $package ){
		if ( ! preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package) )	$this->msg('只支持远程下载');//支持远程下载，ftp下载
		if ( empty($package) ) $this->msg("数据包不存在");
		if ( !defined('DOWNLOAD_DIR') || !defined('BACKUP_DIR')) $this->msg("备份文件目录未定义");
		if ( !is_dir(DOWNLOAD_DIR) ) $this->getFileSys()->smkdir( DOWNLOAD_DIR );
		if ( !file_exists(DOWNLOAD_DIR)){
			if( !$this->getFileSys()->smkdir( DOWNLOAD_DIR ) ) $this->msg('下载目录不可用');
		}
		if( !is_dir(BACKUP_DIR) ) $this->getFileSys()->smkdir( BACKUP_DIR );
		if ( !file_exists(BACKUP_DIR)){
			if( !$this->getFileSys()->smkdir( BACKUP_DIR ) ) $this->msg('备份目录不可用');
		}
		return true;
	}

	/**
	 * 下载文件
	 * @see PW_Upgrade::downLoadPackage()
	 */
	function downLoadPackage( $package, $args =array() ){
		set_time_limit(500);
		return $this->_downLoadUrl( $package, $args);
	}

	/**
	 * 解压文件
	 * @see PW_Upgrade::unPackPackage()
	 */
	function unPackPackage( $package , $deletePackage = true ){
		set_time_limit(500);
		$filesys = $this->getFileSys();
		$tmpdir = DOWNLOAD_DIR . basename($package, '.tmp');
		$package = DOWNLOAD_DIR . $package;
		$result = $filesys->unzipFile($package, $tmpdir);
		if ( $deletePackage ) unlink($package);
		if ( !$result ) $this->msg('解压缩文件失败');
		return basename($tmpdir);
	}

	/**
	 * (non-PHPdoc)
	 * @see PW_Upgrade::checkFile()
	 */
	function checkFile( $package ){
		if(! is_dir( $package )) return true; 
		$md5file = trim(( $package . 'safe.md5' ));
		if( !is_file( $md5file ) ) $this->msg('文件检查md5文件丢失');
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
	 *备份文件|数据库
	 * @see PW_Upgrade::backUp( $tmpdir, $packageName = '' )
	 */
	function backUp( $package, $packageName = '' ){
		if( !is_dir( $package) ) return true;
		set_time_limit(500);
		!$packageName && $packageName = uniqid(time());
		$fileSys = $this->getFileSys();
		$ret = $fileSys->zipFile($package, BACKUP_DIR . $packageName, 'back');
		if(! $ret ) $this->msg('文件备份失败');
		return true;
	}

	/**
	 * 安装数据库｜Xcopy文件
	 * @see PW_Upgrade::installPackage()
	 */
	function installPackage($package, $dir = './'){
		set_time_limit(500);
		$package = DOWNLOAD_DIR . $package;
		$fileSys = $this->getFileSys();
		$ret = $fileSys->scopy($package, realpath(R_P) );
		!$ret && $this->msg('安装失败');
		return true;
	}

	/**
	 * (non-PHPdoc) 安装后期处理
	 * @see PW_Upgrade::after()
	 */
	function after($package){
		$package = DOWNLOAD_DIR . $package;
		$this->getFileSys()->srmdir( $package );
		return true;
	}
	
	/**
	 *
	 * 通过Url下载文件
	 * @param string $url
	 */
	function _downLoadUrl($url, $args){
		!S::isArray($args) && $args = array();
		$args = array_merge(array('timeout' => 300), $args);
		$http = $this->getHttp();
		!$http && $this->msg('Http实例化失败');
		if ( ! $url ) $this->msg('下载地址不能为空');
		$tmpfname = $this->tempnam();
		if ( ! $tmpfname ) $this->msg('下载缓存文件创建失败:' . $tmpfname);
		$handle = @fopen($tmpfname, 'wb');
		if ( ! $handle ) $this->msg('文件打开失败:'.$tmpfname);
		$response = $http->request($url, $args);
		if ( !($response) ) {
			fclose($handle); unlink($tmpfname);	return $response;
		}
		if ( $response['response']['code'] != '200' ){
			fclose($handle); unlink($tmpfname);	$this->msg('远程请求失败：'.trim($response['response']['message']));
		}
		fwrite($handle, $response['body']);
		fclose($handle);
		return basename($tmpfname);
	}

	/**
	 *
	 * 生成下载时的缓存文件
	 * @param String $filename 缓存文件名称
	 * @param String $dir 缓存文件目录
	 */
	function tempnam($filename = '', $dir = ''){
		if(!$dir) $dir = $this->getTempDir();
		!$dir && $this->msg('缓存目录创建失败');
		$filename = basename($filename);
		if ( empty($filename) ) $filename = time().'.tmp';
		$filename = preg_replace('|\..*$|', '.tmp', $filename);
		$filename = $dir .'/'. $filename;
		$this->getFileSys()->touchFile($filename);
		if(! @is_writable($filename) ) $this->msg('文件不可写:'.$filename);
		return $filename;
	}

	/**
	 *
	 * 获取缓存文件目录
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
