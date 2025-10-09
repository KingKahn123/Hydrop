<?php
$ident = $this->sanitizeIdent($name);
$this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, '', 0, true);
$this->SetValue($ident, (float)$v);
} elseif (is_bool($v)) {
$ident = $this->sanitizeIdent($name);
$this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, '', 0, true);
$this->SetValue($ident, (bool)$v);
} elseif (is_string($v)) {
// Store short strings, drop long blobs
if (strlen($v) <= 120) {
$ident = $this->sanitizeIdent($name);
$this->MaintainVariable($ident, $name, VARIABLETYPE_STRING, '', 0, true);
$this->SetValue($ident, $v);
}
}
}
}
}
}


private function sanitizeIdent(string $name): string
{
$ident = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
return substr($ident, 0, 30); // IP‑Symcon ident limit
}


private function findNumber(array $arr, array $keys)
{
foreach ($keys as $k) {
if (isset($arr[$k]) && is_numeric($arr[$k])) return $arr[$k];
}
return null;
}


private function findBool(array $arr, array $keys)
{
foreach ($keys as $k) {
if (isset($arr[$k]) && is_bool($arr[$k])) return $arr[$k];
if (isset($arr[$k]) && ($arr[$k] === 0 || $arr[$k] === 1)) return (bool)$arr[$k];
}
return null;
}


private function isConfigComplete(): bool
{
if (trim($this->ReadPropertyString('BaseUrl')) === '') return false;
if (trim($this->ReadPropertyString('ApiKey')) === '') return false;
if (trim($this->ReadPropertyString('EndpointPath')) === '') return false;
return true;
}
}


// Public script wrappers for buttons/timers
function HYDROP_TestOnce(int $InstanceID)
{
$instance = IPS_GetInstance($InstanceID);
if ($instance['ModuleInfo']['ModuleID'] !== '{7F5C0F6B-7D16-46A2-A7B7-9E6B9F0A3C22}') {
throw new Exception('Wrong module');
}
/** @var HYDROP $obj */
$obj = IPS_GetInstanceObject($InstanceID);
$obj->TestOnce();
}


function HYDROP_Poll(int $InstanceID)
{
/** @var HYDROP $obj */
$obj = IPS_GetInstanceObject($InstanceID);
$obj->Poll();
}
