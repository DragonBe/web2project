<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

$notify_owner = isset($_POST['task_log_notify_owner']) ? $_POST['task_log_notify_owner'] : 0;

$del = w2PgetParam($_POST, 'del', 0);
$isNotNew = $_POST['task_log_id'];
$perms = &$AppUI->acl();
if ($del) {
	if (!$perms->checkModule('task_log', 'delete')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
} elseif ($isNotNew) {
	if (!$perms->checkModule('task_log', 'edit')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
} else {
	if (!$perms->checkModule('task_log', 'add')) {
		$AppUI->redirect('m=public&a=access_denied');
	}
}

$obj = new CTaskLog();

if (!$obj->bind($_POST)) {
	$AppUI->setMsg($obj->getError(), UI_MSG_ERROR);
	$AppUI->redirect();
}

if ($obj->task_log_date) {
	$date = new CDate($obj->task_log_date);
	$obj->task_log_date = $date->format(FMT_DATETIME_MYSQL);
}
$dot = strpos($obj->task_log_hours, ':');
if ($dot > 0) {
	$log_duration_minutes = sprintf('%.3f', substr($obj->task_log_hours, $dot + 1) / 60.0);
	$obj->task_log_hours = floor($obj->task_log_hours) + $log_duration_minutes;
}
$obj->task_log_hours = round($obj->task_log_hours, 3);

// prepare (and translate) the module name ready for the suffix
$AppUI->setMsg('Task Log');
if ($del) {
	if (($msg = $obj->delete())) {
		$AppUI->setMsg($msg, UI_MSG_ERROR);
	} else {
		$AppUI->setMsg('deleted', UI_MSG_ALERT);
	}
	$AppUI->redirect();
} else {
	$obj->task_log_costcode = cleanText($obj->task_log_costcode);
	if (($msg = $obj->store())) {
		$AppUI->setMsg($msg, UI_MSG_ERROR);
		$AppUI->redirect();
	} else {
		$AppUI->setMsg($_POST['task_log_id'] ? 'updated' : 'inserted', UI_MSG_OK, true);
	}
}

$task = new CTask();
$task->load($obj->task_log_task);

$canEditTask = $perms->checkModuleItem('tasks', 'edit', $task_id);
if ($canEditTask) {
	$task->htmlDecode();
	$task->check();
	$task_end_date = new CDate($task->task_end_date);
	$task->task_percent_complete = w2PgetParam($_POST, 'task_percent_complete', null);
	
	if (w2PgetParam($_POST, 'task_end_date', '') != '') {
		$new_date = new CDate($_POST['task_end_date']);
		$new_date->setTime($task_end_date->hour, $task_end_date->minute, $task_end_date->second);
		$task->task_end_date = $new_date->format(FMT_DATETIME_MYSQL);
	}
	
	if ($task->task_percent_complete >= 100 && (!$task->task_end_date || $task->task_end_date == '0000-00-00 00:00:00')) {
		$task->task_end_date = $obj->task_log_date;
	}
	
	$msg = $task->store($AppUI);
	if (is_array($msg)) {
		$AppUI->setMsg($msg, UI_MSG_ERROR, true);
	}
	
	$new_task_end = new CDate($task->task_end_date);
	if ($new_task_end->dateDiff($task_end_date)) {
		$task->addReminder();
	}

	$task->pushDependencies($task->task_id, $task->task_end_date);
}

if ($notify_owner) {
	if ($msg = $task->notifyOwner()) {
		$AppUI->setMsg($msg, UI_MSG_ERROR);
	}
}

// Check if we need to email the task log to anyone.
$email_assignees = w2PgetParam($_POST, 'email_assignees', null);
$email_task_contacts = w2PgetParam($_POST, 'email_task_contacts', null);
$email_project_contacts = w2PgetParam($_POST, 'email_project_contacts', null);
$email_others = w2PgetParam($_POST, 'email_others', '');
$email_extras = w2PgetParam($_POST, 'email_extras', null);

if ($task->email_log($obj, $email_assignees, $email_task_contacts, $email_project_contacts, $email_others, $email_extras)) {
	$obj->store(); // Save the updated message. It is not an error if this fails.
}

$AppUI->redirect('m=tasks&a=view&task_id=' . $obj->task_log_task . '&tab=0#tasklog' . $obj->task_log_id);
