<?php
/**
 * @copyright Copyright (c) 2012 The President and Fellows of Harvard College
 * @license Use of this source code is governed by the LICENSE file found in the root of this project.
 */


require_once "rc4crypt.php";
class Isitestool extends CApplicationComponent {
	
	public $encryptionKey;
	
	const SUPER = 21;
	const OWNER = 17;
	const ADMIN = 14;
	const ENROLLEE = 9;
	const GUEST = 7;
		
		
	public function getDecryptedUserId($userid) {
        
		$plaintext = rc4crypt::decrypt($this->encryptionKey, pack('H*', $userid));
        //error_log("encrypted=$userid decrypted=$plaintext");


        $decrypted_id = $timestamp = ''; 
        if($plaintext != '') {
            list($decrypted_id, $timestamp) = explode('|', $plaintext, 2); 
        }   

        return $decrypted_id;
    }
	
	/**
	 * gets the idphoto url
	 * the base for this url is http://isites.harvard.edu/idphoto
	 * however, this is using a relative path for simplicity's sake
	 * 
	 * Expected output is something like:
 	 * http://isites.harvard.edu/idphoto/818044447e8a92af22de3e631390807356d22e7be7475e242b4a18d9f9340e0fc12a5a348c454dd8355b940c81eb93736d90467df8df576f523f5506dc814fd90f7a3041fc906a0f3649c7cbb0a8310ea354f47fd8cddeb2f572755b331ac3bb_50.jpg	
	 *
	 * @param number $huid
	 * @return string url (relative path) for the idphoto
	 */
	public function getPhotoUrl($huid){
		Yii::import('application.vendors.phpseclib.*');
		require_once('Crypt/RC4.php');
				
		//$huid = '80719647';
		//$encrypted_id = Yii::app()->getRequest()->getParam('userid');
		//$huid = $encrypted_id;
		$ip = $_SERVER['REMOTE_ADDR'];
		$ip = Yii::app()->getRequest()->getParam('remoteAddr');
		$size = 128;
		$ext = 'jpg';

		$key = Yii::app()->params['photoKey'];
		$randomstring = "1234567890123456789012345678901234567890123456789012345678901";
		$rawdata = "$huid|$ip|".time()."|$randomstring";

		$rc4 = new Crypt_RC4();
		$rc4->setKey($key);
		$data = unpack('H*', $rc4->encrypt($rawdata));

		$redone = $rc4->decrypt(pack('H*', $data[1]));
		
		//error_log(var_export($data, 1));
		//error_log($redone);
		$url = "/idphoto/".$data[1]."_".$size.".jpg";
		return $url;
	}

	/**
	* Returns the list of permissions as an array
	*/
	private function getPermissions(){
		$perm_list = array();
		$perm_str = isset($_REQUEST['permissions']) ? $_REQUEST['permissions'] : '';
		if(isset($perm_str) && $perm_str !== ''){
			$perm_list = preg_split('/,/', $perm_str);
		}
		return $perm_list;
	}
	
	
	public function isSuper() {
		return in_array(self::SUPER, $this->getPermissions());
	}
	public function isOwner() {
		return in_array(self::OWNER, $this->getPermissions());
	}
	public function isAdmin() {
		return in_array(self::ADMIN, $this->getPermissions());
	}
	public function isEnrollee() {
		return in_array(self::ENROLLEE, $this->getPermissions());
	}
	public function isGuest() {
		return in_array(self::GUEST, $this->getPermissions());
	}
	
	public function url($viewPath = null, $viewQuery = array(), $viewFragment = null) {
		
		$host = Yii::app()->getRequest()->getParam('urlRoot');
		$keyword = Yii::app()->getRequest()->getParam('keyword');
		$page_id = Yii::app()->getRequest()->getParam('pageid');
		$page_content_id = Yii::app()->getRequest()->getParam('pageContentId');
		$topic_id = Yii::app()->getRequest()->getParam('topicId');
		$state = Yii::app()->getRequest()->getParam('state');
        
		$parts = array(
			'scheme' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http',
		    'host' => isset($host) ? $host : 'isites.harvard.edu',
		    'path' => 'icb/icb.do',
		    'query' => '',
		    'fragment' => $viewFragment,
		);
    
		$mergeQuery = array(
			'state' => $state,
			'keyword' => $keyword
		);

		if($state === 'popup') {
			$viewParams = $this->_queryAsViewParams($viewQuery);
			$mergeQuery = array_merge($mergeQuery, array(
				'topicid' => $topic_id, // Note the spelling: topicid, NOT topicId
				'view' => $viewPath)
			);
			$mergeQuery = array_merge($mergeQuery, $viewParams);
		} else {
			// pass view params back to our app via the "panel" query
			$panelView = $viewPath;
			$panelParams = array();
			if(!empty($viewQuery)) {
		    	foreach($viewQuery as $queryKey => $queryVal) {
					$panelParams[] = "$queryKey=$queryVal";
				}
				$panelView .= '?' . implode('&', $panelParams);
			}
            
			$mergeQuery = array_merge($mergeQuery, array(
				'topicId' => $topic_id,
				'pageContentId' => $page_content_id,
				'pageid' => $page_id,
				'panel' => $page_content_id.':r'.$panelView
			));
		}

		$parts['query'] = $mergeQuery;
        
		$full_url = $parts['scheme'] . '://' . $parts['host'] . '/' . $parts['path'] . '?' . http_build_query($mergeQuery);
		if(isset($parts['fragment'])) {
			$full_url .= '#' . $parts['fragment'];
		}
		$full_url .= "#quizmo-$topic_id";
		
		return htmlspecialchars($full_url, ENT_QUOTES, 'UTF-8');
		
	
	}
	
	public function ajaxurl($viewPath = null, $viewQuery = array(), $viewFragment = null) {
		
		$host = Yii::app()->getRequest()->getParam('urlRoot');
		$keyword = Yii::app()->getRequest()->getParam('keyword');
		$page_id = Yii::app()->getRequest()->getParam('pageid');
		$page_content_id = Yii::app()->getRequest()->getParam('pageContentId');
		$topic_id = Yii::app()->getRequest()->getParam('topicId');
		$state = Yii::app()->getRequest()->getParam('state');
        
		$parts = array(
			'scheme' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http',
		    'host' => isset($host) ? $host : 'isites.harvard.edu',
		    'path' => 'icb/ajax'.$viewPath,
		    'query' => '',
		    'fragment' => $viewFragment,
		);
    
		$mergeQuery = array(
			'state' => $state,
			'keyword' => $keyword
		);

		if($state === 'popup') {
			$viewParams = $this->_queryAsViewParams($viewQuery);
			$mergeQuery = array_merge($mergeQuery, array(
				'topicid' => $topic_id, // Note the spelling: topicid, NOT topicId
				'view' => $viewPath)
			);
			$mergeQuery = array_merge($mergeQuery, $viewParams);
		} else {
			// pass view params back to our app via the "panel" query
			$panelView = $viewPath;
			$panelParams = array();
			if(!empty($viewQuery)) {
		    	foreach($viewQuery as $queryKey => $queryVal) {
					$panelParams[] = "$queryKey=$queryVal";
				}
				$panelView .= '?' . implode('&', $panelParams);
			}
            
			$mergeQuery = array_merge($mergeQuery, array(
				'topicId' => $topic_id,
				'pageContentId' => $page_content_id,
				//'pageid' => $page_id,
				//'panel' => $page_content_id.':r'.$panelView
			));
		}

		$parts['query'] = $mergeQuery;
		$full_url = $parts['scheme'] . '://' . $parts['host'] . '/' . $parts['path'] . '?' . http_build_query($mergeQuery);
		if(isset($parts['fragment'])) {
			$full_url .= '#' . $parts['fragment'];
		}
		
		return htmlspecialchars($full_url, ENT_QUOTES, 'UTF-8');
		
	
	}
}	
?>
