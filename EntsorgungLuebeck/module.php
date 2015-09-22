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
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_schwarz") !== false) {
					$wasteTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("WasteTime"), $wasteTime);
			
			$paperTime = 0;
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_blau") !== false) {
					$paperTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("PaperTime"), $paperTime);
			
			$bioTime = 0;
			foreach($result as $item) {
				if(strpos($item['feiertag'], "tonne_braun") !== false) {
					$bioTime = strtotime($item['tag'].". ".$item['monat']);
					break;
				}
			}
			SetValue($this->GetIDForIdent("BioTime"), $bioTime);
			
		}
	
	}

?>
