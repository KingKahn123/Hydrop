<?php
class HYDROP extends IPSModule
{
public function Create()
{
parent::Create();
// Properties
$this->RegisterPropertyInteger('PollSeconds', 60);


// Timer → ruft per RequestAction die Poll-Methode auf
$this->RegisterTimer('PollTimer', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", 0);');
}


public function ApplyChanges()
{
parent::ApplyChanges();


// Intervall setzen (mind. 10s)
$seconds = (int)$this->ReadPropertyInteger('PollSeconds');
if ($seconds < 10) $seconds = 10;
$this->SetTimerInterval('PollTimer', $seconds * 1000);


$this->SendDebug('HYDROP', 'Timer interval set to ' . $seconds . 's', 0);
$this->SetStatus(104); // Aktiv
}


public function RequestAction($Ident, $Value)
{
switch ($Ident) {
case 'Poll':
$this->Poll();
return true;
case 'TestOnce':
$this->SendDebug('HYDROP', 'Manual test requested', 0);
$this->Poll();
return true;
default:
throw new Exception('Unknown action ' . $Ident);
}
}


private function Poll()
{
$this->SendDebug('HYDROP', 'Timer fired at ' . date('Y-m-d H:i:s'), 0);
// Hier später: REST-Call & Variablen-Update
}
}
