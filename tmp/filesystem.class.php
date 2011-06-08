<?php
!defined('P_W') && exit('Forbidden');
/**
 *
 * �ļ�������
 * @author hu.liaoh
 */
Class PW_FileSystem{
	/**
	 *
	 * ��ѹ�ļ�
	 * @param string $package
	 * @param string $dir
	 */
	function unzipFile( $package , $dir ){
		@ini_set('memory_limit', '256M');
		if( class_exists('ZipArchive')){
			return $this->_unzipFileZiparchive( $package, $dir );
		}
		return $this->_unzipFilePclzip( $package, $dir );
	}

	/**
	 *
	 * ѹ���ļ�
	 * @param String $package ѹ���ļ�·��
	 * @param String $packageName ѹ�������ƣ���:test,ѹ��Ϊzipѹ����
	 * @param String $localname ѹ������Ŀ¼�ļ���
	 */
	function zipFile( $package , $packageName, $localname ){
		if( class_exists('ZipArchive') ){
			return $this->_zipFileZiparchive( $package, $packageName.'.zip' , $localname);
		}
		return $this->_zipFilePclzip( $package, $packageName.'.zip' , $localname);
	}

	/**
	 *
	 * ZipArchive ��ѹѹ����
	 * @param string $package
	 * @param string $dir
	 */
	function _unzipFileZiparchive( $package, $dir){
		$archive = new ZipArchive();
		$zopen = $archive->open($package, ZipArchive::CHECKCONS);
		if( !$zopen) exit('ZipArchive open failed');
		$list = $archive->extractTo($dir);
		$archive->close();
		return $list;
	}

	/**
	 *
	 * Ziparchive��ʽѹ���ļ�
	 * @param String $package ѹ���ļ�·��
	 * @param String $packageName ѹ�������ƣ��磺test.zip
	 * @param String $localname ѹ��Ҫ���ļ�������
	 */
	function _zipFileZiparchive( $package, $packageName, $localname){
		$archive = new ZipArchive();
		$packageName = $packageName;
		$zopen = $archive->open( $packageName, ZipArchive::CREATE );
		if( !$zopen ) exit('ZipArchive open failed');
		$list = $this->_archiveAddDir( &$archive, $package, $localname,$packageName );
		if(!$list) exit('�ļ�ѹ��ʧ��');
		$archive->close();
		return $list;
	}

	/**
	 *
	 * ZipArchive���Ŀ¼��Ŀ¼�������ļ�
	 * @param Object $archive ZipArchive ��������
	 * @param String $path �ļ�Ŀ¼·��
	 * @param String $localname ѹ������Ŀ¼�ļ�����
	 * @param String $packageName ѹ��������
	 */
	function _archiveAddDir( $archive, $path, $localname, $packageName) {
		if(!$archive->addEmptyDir($localname)) return false;
		$nodes = glob($path . '/*');
		foreach ($nodes as $node) {
			// see: http://bugs.php.net/bug.php?id=40494
			// and: http://pecl.php.net/bugs/bug.php?id=9443
			if($archive->numFiles % 200 == 0){
				$archive->close();
				if(!$archive->open($packageName, ZipArchive::CREATE)) return false;
			}
			$partes = pathinfo($node);
			if (is_dir($node)) {
				$this->_archiveAddDir($archive, $path."/".$partes["basename"], $localname."/".$partes["basename"], $packageName);
			} else if (is_file($node))  {
				if(!$archive->addFile(str_replace("\\","/",$node), $localname . '/' .$partes['basename'])) return false;
			}
		}
		return true;
	}

	/**
	 *
	 * PclZip ��ѹѹ����
	 * @param string $package
	 * @param string $dir
	 */
	function _unzipFilePclzip( $package, $dir ){
		require_once( 'pclzip.class.php' );
		$archive = new PclZip($package);
		$list = $archive->extract(PCLZIP_OPT_PATH, $dir);
		if (!$list) exit($archive->errorInfo(true));
		return $list;
	}

	/**
	 *
	 * Pclzip��ʽѹ���ļ�
	 * @param String $package ѹ���ļ�·��
	 * @param String $packageName ѹ�������ƣ��磺test.zip
	 * @param String $localname ѹ��Ҫ���ļ�������
	 */
	function _zipFilePclzip( $package, $packageName , $localname){
		require_once( 'pclzip.class.php' );
		$archive = new PclZip($packageName);
		$remove = $dir = $package;
		if (substr($dir, 1, 1) == ':') $remove = substr($dir, 2);
		$list = $archive->create($dir, PCLZIP_OPT_REMOVE_PATH, $remove, PCLZIP_OPT_ADD_PATH, $localname);
		if ($list == 0) {
			exit("Error : ".$archive->errorInfo(true));
		}
	}

	/**
	 *
	 * ����Ŀ¼��֧�ֵݹ鴴��Ŀ¼
	 * @param String $dirName Ҫ������Ŀ¼
	 * @param int $mode Ŀ¼Ȩ��
	 */
	function smkdir($dirName, $mode = 0777) {
		$dirs = explode('/', str_replace('\\', '/', $dirName));
		$dir='';
		foreach ($dirs as $part) {
			$dir.=$part.'/';
			if (!is_dir($dir) && strlen($dir)>0){
				if(!@mkdir($dir, $mode)) return false;
				if(!@chmod($dir, $mode)) return false;
			}
		}
		return true;
	}

	/**
	 *
	 * �����ļ������Ŀ¼�����ڣ����Զ�����Ŀ¼
	 * @param String $filename �ļ�����
	 * @param Int $mode �ļ�Ȩ��
	 */
	function touchFile($filename, $mode = 0777){
		if( !file_exists(dirname($filename)) ) $this->smkdir(dirname($filename));
		if( !touch( $filename) ) return false;
		if( !chmod($filename, $mode) ) return false;
		return true;
	}

	/**
	 *
	 * ɾ��Ŀ¼��֧�ֵݹ�ɾ���༶Ŀ¼
	 * @param String $dir Ŀ¼
	 */
	function srmdir( $dir ){
		if (!file_exists($dir)) return true;
		if (!is_dir($dir) || is_link($dir)) return unlink($dir);
		foreach (scandir($dir) as $item) {
			if ($item == '.' || $item == '..') continue;
			if (!$this->srmdir($dir . "/" . $item)) {
				chmod($dir . "/" . $item, 0777);
				if (!$this->srmdir($dir . "/" . $item)) return false;
			};
		}
		return rmdir($dir);
	}

	/**
	 *
	 * �����ļ�
	 * @param String $src ԴĿ¼
	 * @param String $dst Ŀ��kĿ¼
	 */
	function scopy($src,$dst) {
		$dir = opendir($src);
		if(!$this->smkdir($dst)) return false;
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					$this->smkdir($dst . '/' . $file);
					$this->scopy($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					if(!copy($src . '/' . $file,$dst . '/' . $file)) return false;
				}
			}
		}
		closedir($dir);
		return true;
	}

	/**
	 *
	 * ��ȡmd5_file�ļ�����
	 * @param String $dir Ŀ¼
	 * @param String $ext ����ļ���׺��
	 * @param Bool $sub �Ƿ�����Ŀ¼
	 */
	function safefile(&$md5_a, $dir, $ext='', $sub=1){
		$exts = '/(' . $ext . ')$/i';
		$fp = opendir( $dir );
		while($filename = readdir( $fp )){
			$path = $dir.$filename;
			if($filename != '.' && $filename != '..' && (preg_match($exts, $filename ) || $sub && is_dir( $path ))){
				if($sub && is_dir($path)){
					$this->safefile(&$md5_a, $path.'/',$ext, $sub);
				} else{
					$md5_a[$path] = md5_file($path);
				}
			}
		}
		closedir($fp);
	}

	/**
	 * 
	 * ����md5�ļ�����ļ�
	 * @param Array $check ���
	 * @param String $keyword 
	 * @param String $dir
	 * @param String $sub
	 */
	function checkfile( &$check, $keyword, $dir, $sub ){
		$fp = opendir( $dir );
		while($filename = readdir( $fp )){
			$path = $dir . $filename;
			if( $filename != '.' && $filename != '..' ){
				if( is_dir( $path ) ){
					$sub && $this->checkfile( &$check, $keyword, $path . '/', $sub );
				} elseif( preg_match( '/(\.php|\.php3|\.htm|\.js)$/i', $filename ) && filesize( $path ) < 1048576 ){
					$a = strtolower( readover( $path ) );
					if( strpos( $a, $keyword ) !== false ){
						$check[$path] = 1;
					}
				}
			}
		}
		closedir($fp);
	}
}