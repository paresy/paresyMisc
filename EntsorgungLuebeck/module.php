<?

	class EntsorgungLuebeck extends IPSModule
	{

		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyString("streetName", "Willy-Brandt-Allee");
			$this->RegisterPropertyString("streetNumber", "31");
        
			
		}		
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableInteger("WasteTime", "Restmuell", "~UnixTimestamp");
			$this->RegisterVariableInteger("BioTime", "Biotonne", "~UnixTimestamp");
			$this->RegisterVariableInteger("PaperTime", "Papiertonne", "~UnixTimestamp");
			$this->RegisterTimer("RequestInfo", 'EL_RequestInfo($_IPS[\'TARGET\']);');
			$this->RequestInfo();
			
		}
	
		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* EL_RequestInfo($id);
		*
		*/
		public function RequestInfo()
		{
		
			
			$strasse = $this->ReadPropertyString("streetName");
			$hausnr = $this->ReadPropertyString("streetNumber");
			if (trim($strasse) == "")
				return;
			$str = base64_encode('a:3:{s:3:"STR";s:'.strlen($strasse).':"'.$strasse.'";s:4:"YEAR";s:4:"'.date("Y").'";s:3:"HNR";s:'.strlen($hausnr).':"'.$hausnr.'";}');
			$buffer = file_get_contents("http://luebeck.abfallkalender.insert-infotech.de/kalender.php?BaseString=".$str."%3D");

			if(strpos($buffer, "Leerungsdaten") !== false) {
				echo "Ungültige Adresse!";
				return;
			}
			
			//kill everything before the interesting table
			$buffer = stristr($buffer, '<div class="kw_table">');

			$i = 0;

			
			while(true) {
				//fetch div
				$buffer = stristr($buffer, '<div class="kw_td_');
				if($buffer === false)
					break;

				$name = substr($buffer, 18, strpos($buffer, " ", 18)-18);
				$buffer = stristr($buffer, '>');
				$value = substr($buffer, 1, strpos($buffer, "</div>")-1);

				if($name == "ueberschrift")
					continue; //skip

				if($name == "wochentag")
					$i++; //increment counter
				
				$result[$i][$name] = $value;
			}
			
			//var_dump($result);
			
			$wasteTime = 0;
			$nextTime = 0;
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_schwarz") !== false) {
					$wasteTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("WasteTime"), $wasteTime);
			if ($wasteTime <> 0)
				$nextTime = $wasteTime;
			
			$paperTime = 0;
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_blau") !== false) {
					$paperTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("PaperTime"), $paperTime);
			if (($paperTime <> 0) and ( $nextTime > $paperTime ))
				$nextTime = $paperTime;
			
			$bioTime = 0;
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_braun") !== false) {
					$bioTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("BioTime"), $bioTime);
			if (($bioTime <> 0) and ( $nextTime > $bioTime ))
				$nextTime = $bioTime;

			if ($nextTime <> 0)
                            $this->SetTimer('RequestInfo', $nextTime + 86400);
			else
                            $this->SetTimer('RequestInfo', 86400 + time());
		}
                
		//Woarkaround Timer
		protected function RegisterTimer($Name, $Script)
		{
			$id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
			if ($id === false)
				$id = 0;

			if ($id > 0)
			{
				if (!IPS_EventExists($id))
					throw new Exception("Ident with name " . $Name . " is used for wrong object type");
	
				if (IPS_GetEvent($id)['EventType'] <> 1)
				{
					IPS_DeleteEvent($id);
					$id = 0;
				}
			}

			if ($id == 0)
			{
				$id = IPS_CreateEvent(1);
				IPS_SetParent($id, $this->InstanceID);
				IPS_SetIdent($id, $Name);
			}
			IPS_SetName($id, $Name);
			IPS_SetHidden($id, true);
			IPS_SetEventScript($id, $Script);
			IPS_SetEventActive($id, false);
		}

		protected function UnregisterTimer($Name)
		{
			$id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
			if ($id > 0)
			{
				if (!IPS_EventExists($id))
					throw new Exception('Timer not present');
				IPS_DeleteEvent($id);
			}
		}

		protected function SetTimer($Name, $TargetTime)
		{
			$id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
			if ($id === false)
				throw new Exception('Timer not present');
			if (!IPS_EventExists($id))
				throw new Exception('Timer not present');
                        $Event = IPS_GetEvent($id);
			if ($TargetTime < time())
			{
				if ($Event['EventActive'])
					IPS_SetEventActive($id, false);
			}
			else
			{
                             
				IPS_SetEventCyclic($id, 1, 0, 0, 0, 0, 0);
                                IPS_SetEventCyclicDateFrom($id,(int)date("j",$TargetTime),(int)date("n",$TargetTime),(int)date("o",$TargetTime));
                                IPS_SetEventCyclicDateTo($id,(int)date("j",$TargetTime),(int)date("n",$TargetTime),(int)date("o",$TargetTime));
                                IPS_SetEventCyclicTimeFrom($id,(int)date("H",$TargetTime),(int)date("i",$TargetTime),(int)date("s",$TargetTime));
                                IPS_SetEventCyclicTimeTo($id,(int)date("H",$TargetTime),(int)date("i",$TargetTime),(int)date("s",$TargetTime));
                                if (!$Event['EventActive'])
					IPS_SetEventActive($id, true);
			}
		}
			
		
	
	}

?>
