<?php
namespace Stanford\RetrieveHistoryData;

require_once "emLoggerTrait.php";

class RetrieveHistoryData extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

}
