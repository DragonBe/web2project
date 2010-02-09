<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

$obj = new CContact();
$msg = '';

$contact_id = (int) w2PgetParam($_POST, 'contact_id', 0);
$isNotNew = $_POST['contact_id'];
$del = (int) w2PgetParam($_POST, 'del', 0);
$perms = &$AppUI->acl();

if (isset($del) && $del) {
	if (!$perms->checkModule('contacts', 'delete')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
} elseif ($isNotNew) {
	if (!$perms->checkModule('contacts', 'edit')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
} else {
	if (!$perms->checkModule('contacts', 'add')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
}

$notifyasked = w2PgetParam($_POST, 'contact_updateask', 0);
if ($notifyasked != 0) {
	$notifyasked = 1;
}

if ($contact_id) {
	$obj->load($contact_id);
}

if (!$obj->bind($_POST)) {
	$AppUI->setMsg($obj->getError(), UI_MSG_ERROR);
	$AppUI->redirect();
}

// prepare (and translate) the module name ready for the suffix
$AppUI->setMsg('Contact');
if ($del) {
	if (($msg = $obj->delete($AppUI))) {
		$AppUI->setMsg($msg, UI_MSG_ERROR);
		$AppUI->redirect();
	} else {
		$AppUI->setMsg('deleted', UI_MSG_ALERT, true);
		$AppUI->redirect('m=contacts');
	}
} else {
	if (($result = $obj->store($AppUI))) {
	  if (is_array($result)) {
      $AppUI->setMsg($result, UI_MSG_ERROR, true);
      $AppUI->holdObject($obj);
      $AppUI->redirect('m=contacts&a=addedit');
    }
	} else {
		$custom_fields = new CustomFields($m, 'addedit', $obj->contact_id, 'edit');
		$custom_fields->bind($_POST);
		$sql = $custom_fields->store($obj->contact_id); // Store Custom Fields

		$updatekey = $obj->getUpdateKey();
		$contact_change = false;
		if ($notifyasked && !$updatekey) {
			$rnow = new CDate();
			$obj->contact_updatekey = MD5($rnow->format(FMT_DATEISO));
			$obj->contact_updateasked = $rnow->format(FMT_DATETIME_MYSQL);
			$obj->contact_lastupdate = '';
			$obj->updateNotify();
			$contact_change = true;
		} elseif ($notifyasked && $updatekey) {
		} elseif ($obj->contact_updatekey != '') {
			$obj->contact_updatekey = '';
			$contact_change = true;
		}
		if ($contact_change) {
			$obj->store($AppUI);
		}	

		$AppUI->setMsg($isNotNew ? 'updated' : 'added', UI_MSG_OK, true);
	}
	$AppUI->redirect();
}