<?

	class EntsorgungZVO extends IPSModule
	{

		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyString("city", "30");
			
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableInteger("WasteTime", "Restmuell", "~UnixTimestampDate");
			$this->RegisterVariableInteger("BioTime", "Biotonne", "~UnixTimestampDate");
			$this->RegisterVariableInteger("RecycleTime", "Gelber Sack", "~UnixTimestampDate");
			$this->RegisterVariableInteger("PaperTime", "Papiertonne", "~UnixTimestampDate");

		}
		
		public function GetConfigurationForm() {
			
			$json = json_decode(file_get_contents(__DIR__ . "/form.json"), true);

			$cities = json_decode(file_get_contents("https://www.zvo.com/api/wastecollection/cities"));
			
			foreach($cities as $city) {
				$json["elements"][0]["options"][] = [
					"caption" => $city->name,
					"value" => strval($city->id)
				];
			}
			
			return json_encode($json);
			
		}
	
		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* EZVO_RequestInfo($id);
		*
		*/
		public function RequestInfo()
		{
		
			$data = array(
				'street' => '',
				'city' => $this->ReadPropertyString("city")
			);

			$context = stream_context_create(array(
				'http' => array(
					'method' => 'POST',
					'header' => "Content-Type: application/json\r\n",
					'content' => json_encode($data)
				)
			));
			
			$json = file_get_contents("https://www.zvo.com/api/wastecollection/wastecollection", false, $context);

			$this->SendDebug("Collections", $json, 0);
			
			$collection = json_decode($json, true)[0];

			$data = array(
				'collection' => $collection["id"]
			);

			$context = stream_context_create(array(
				'http' => array(
					'method' => 'POST',
					'header' => "Content-Type: application/json\r\n",
					'content' => json_encode($data)
				)
			));
			
			$json = file_get_contents("https://www.zvo.com/api/wastecollection/wastecollectiondates", false, $context);
			
			$this->SendDebug("Dates", $json, 0);
			
			$color = [
				"default" => 0,
				"blue" => 0
			];
			foreach(json_decode($json, true) as $date) {
				$ts = strtotime($date["collect_date"]["date"]);
				if($ts > time()) {
					if($color["default"] == 0) {
						$color["default"] = $ts;
					}
					if($date["color"] == $collection["color"]) {
						if($color["blue"] == 0) {
							$color["blue"] = $ts;
						}
					}
				}
			}

			$this->SendDebug("Pickup", json_encode($color), 0);
			
			SetValue($this->GetIDForIdent("WasteTime"), $color["default"]);
			SetValue($this->GetIDForIdent("BioTime"), $color["default"]);
			SetValue($this->GetIDForIdent("RecycleTime"), $color["default"]);
			SetValue($this->GetIDForIdent("PaperTime"), $color["blue"]);
			
		}
	
	}

?>
