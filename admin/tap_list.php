<?php
require_once __DIR__.'/header.php';
$htmlHelper = new HtmlHelper();
$tapManager = new TapManager();
$beerManager = new BeerManager();
$kegManager = new KegManager();

$config = getAllConfigs();

$reconfig = false;
if( isset($_POST['enableTap']) && $_POST['enableTap'] != ""){
	//The element holds the tap Id
	$tapManager->enableTap($_POST['enableTap']);
	file_get_contents('http://' . $_SERVER['SERVER_NAME'] . '/admin/trigger.php?value=valve');
}

if( isset($_POST['disableTap']) && $_POST['disableTap'] != ""){
	//The element holds the tap Id
	$tapManager->disableTap($_POST['disableTap']);
	file_get_contents('http://' . $_SERVER['SERVER_NAME'] . '/admin/trigger.php?value=valve');
}

if (isset ( $_POST ['saveTapConfig'] )) {
	$ii = 0;
	while(isset($_POST ['tapId'][$ii]))
	{
		$id = $_POST ['tapId'][$ii];
		$tap = $tapManager->GetById($id);		
		$tap->set_startAmount($_POST['startAmount'][$ii]);
		$tap->set_currentAmount($_POST['currentAmount'][$ii]);
		if (isset ( $_POST ['tapNumber'][$ii] )) {
			$tap->set_tapNumber($_POST ['tapNumber'][$ii]);
		}
		$kegSelArr = explode("~", $_POST['kegId'][$ii]);
		//Select array is kegid~beerid(in keg)~tapId(keg is on)
		$kegId = null;
		if(count($kegSelArr) > 0 && isset($kegSelArr[0]))$kegId = $kegSelArr[0];
		if($kegId){			
			if( ( !isset($kegSelArr[1]) || !$kegSelArr[1] || $tap->get_beerId() != $_POST['beerId'][$ii] ) ||
			    ( !isset($kegSelArr[2]) || !$kegSelArr[2] || $tap->get_kegId() != $kegId ) ){
			$tapManager->tapKeg($tap, $kegId, $_POST['beerId'][$ii]);		
			}
		}else if($tap->get_kegId()){
			//User indicated the tap was untapped
			$tapManager->closeTap($tap, false);	
		}
		$tapManager->Save($tap);
		
		$tapNumber = "";
		$flowpin = 0;
		$valveon = 0;
		$valvepin = 0;
		$countpergallon = 0;
	
		if (isset ( $_POST ['flowpin'][$ii] )) {
			$flowpin = $_POST ['flowpin'][$ii];
		}
	
		if (isset ( $_POST ['valvepin'][$ii] )) {
			$valvepin = $_POST ['valvepin'][$ii] * ($_POST ['valvepinPi'][$id]?-1:1);
		}
	
		if (isset ( $_POST ['countpergallon'][$ii] )) {
			$countpergallon = $_POST ['countpergallon'][$ii];
		}
	
		$tapManager->saveTapConfig ( $id, $flowpin, $valvepin, $valveon, $countpergallon );
		$ii++;
	}
	$reconfig = true;
} 
if (isset ( $_POST ['saveSettings'] )) {
	if (isset ( $_POST ['numberOfTaps'] )) {
		$oldTapNumber = $tapManager->getNumberOfTaps();
		$newTapNumber = $_POST ['numberOfTaps'];
		if( !isset($oldTapNumber) || $newTapNumber != $oldTapNumber) {
			$tapManager->updateNumberOfTaps ( $newTapNumber );
		}
		unset($_POST ['numberOfTaps']);
	} 
	} 
if (isset ( $_POST ['saveSettings'] ) || isset ( $_POST ['configuration'] )) {
	setConfigurationsFromArray($_POST, $config);
	if (isset ( $_POST ['saveSettings'] ) )$reconfig = true;
}

if($reconfig){
	file_get_contents ( 'http://' . $_SERVER ['SERVER_NAME'] . '/admin/trigger.php?value=all' );
}

$activeTaps = $tapManager->GetAllActive();
$numberOfTaps = count($activeTaps);
$beerList = $beerManager->GetAllActive();
$kegList = $kegManager->GetAllActive();
?>

<body>
	<!-- Start Header  -->
<?php
include 'top_menu.php';
?>
	<!-- End Header -->
	<!-- Top Breadcrumb Start -->
	<div id="breadcrumb">
		<ul>	
			<li><img src="img/icons/icon_breadcrumb.png" alt="Location" /></li>
			<li><strong>Location:</strong></li>
			<li class="current">Tap List</li>            
		</ul>
	</div>
	<!-- Top Breadcrumb End --> 
	<!-- Right Side/Main Content Start -->
	<div id="rightside">
		<div class="contentcontainer med left" >
		<?php $htmlHelper->ShowMessage(); ?>
              
        <a onClick="toggleSettings(this, 'settingsDiv')" class="collapsed heading">Settings</a>
		
		<div id="settingsDiv" style="<?php echo (isset($_POST['settingsExpanded'])?$_POST['settingsExpanded']:'display:none'); ?>">
        
        <form id="configuration" method="post">
        	<input type="hidden" name="configuration" id="configuration" />
        	<input type="hidden" name="settingsExpanded" id="settingsExpanded" value="<?php echo (isset($_POST['settingsExpanded'])?$_POST['settingsExpanded']:'display:none'); ?>" />
            <table class="contentbox" style="width:100%; border:0;" >
            	<tr>
			<?php
			    $result = getTapConfigurableConfigs();
				foreach($result as $row) {
					echo '<td>';
					echo '	<input type="hidden" name="' . $row['configName'] . '" value="0"/>';
					echo '	<input type="checkbox" ' . ($row['configValue']?'checked':'') . ' name="' . $row['configName'] . '" value="1" onClick="this.form.submit()">'.$row['displayName']."&nbsp;\n";
					echo '</td>';
				}
			?>        
            	</tr>
            </table>
        </form>
        
		<form method="POST" name="settings" >        
	<?php if($config[ConfigNames::UseFlowMeter]) { ?>
    		<input type="hidden" name="alamodeConfig" id="alamodeConfig" />
    <?php } ?>
	<?php if($config[ConfigNames::UseFanControl]) { ?>
			<input type="hidden" name="fanConfig" id="fanConfig" />
    <?php } ?>
	<?php if($config[ConfigNames::UseTapValves]) { ?>
			<input type="hidden" name="tapValveConfig" id="tapValveConfig" />
	<?php } ?>
			<table class="contentbox" style="width:100%; border:0;" >
				<thead>
					<tr>
						<th></th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>

	<?php if($config[ConfigNames::UseFlowMeter]) { ?>
					<tr>
						<td><b>Alamode Setup:</b></td>
						<td><b>Pour Message Delay:</b><br/>The number of milliseconds after pulses stop to send the pour</td>
						<td><input type="text" name="alamodePourMessageDelay" class="smallbox" value="<?php echo ($config[ConfigNames::AlamodePourMessageDelay]) ?>"></td>
					</tr>
					<tr>
						<td><b>Alamode Setup:</b></td>
						<td><b>Pour Trigger Count:</b><br/> The minimum flow meter count to start a pour</td>
						<td><input type="text" name="alamodePourTriggerCount" class="smallbox" value="<?php echo ($config[ConfigNames::AlamodePourTriggerCount]) ?>"></td>
					</tr>
					<tr>
						<td><b>Alamode Setup:</b></td>
						<td><b>Kick Trigger Count:</b><br/> The flow meter count within one millisecond that indicates a kick</td>
						<td><input type="text" name="alamodeKickTriggerCount" class="smallbox" value="<?php echo ($config[ConfigNames::AlamodeKickTriggerCount]) ?>"></td>
					</tr>
					<tr>
						<td><b>Alamode Setup:</b></td>
						<td><b>Update Trigger Count:</b><br/>The flow meter count after which a internal update is reported</td>
						<td><input type="text" name="alamodeUpdateTriggerCount" class="smallbox" value="<?php echo ($config[ConfigNames::AlamodeUpdateTriggerCount]) ?>"></td>
					</tr>
		<?php } ?>
		<?php if($config[ConfigNames::UseFanControl]) { ?>
					<tr>
						<td><b>Fan Setup:</b></td>
						<td><b>Fan Pin (GPIO):</b><br>The pin that powers the fan</td>
						<td><input type="text" name="useFanPin" class="smallbox" value="<?php echo $config[ConfigNames::UseFanPin] ?>" /></td>
					</tr>
					<tr>
						<td><b>Fan Setup:</b></td>
						<td><b>Fan Interval (mins):</b><br/>The interval with which the fan will be triggered<br/>(every x minutes, turn the fan on).</td>
						<td><input type="text" name="fanInterval" class="smallbox"
							value="<?php echo $config[ConfigNames::FanInterval] ?>" /></td>
					</tr>
					<tr>
						<td><b>Fan Setup:</b></td>
						<td><b>Fan Duration (mins):</b><br/>The duration the fan will run after it has been triggered.<br/>If Interval is less than Duration, the fan always runs. If Duration is zero or less, the fan never runs.</td>
						<td><input type="text" name="fanOnTime" class="smallbox" value="<?php echo $config[ConfigNames::FanOnTime] ?>" /></td>
					</tr>
	<?php } ?>
	<?php if($config[ConfigNames::UseTapValves]) { ?>
					<tr>
						<td><b>Tap Valves Setup:</b></td>
						<td><b>Pour Shutoff Count:</b><br/>The flow meter count in one pour after which a tap is shutoff (0 to turn off) </td>
						<td><input type="text" name="pourShutOffCount" class="smallbox"	value="<?php echo ($config[ConfigNames::PourShutOffCount]) ?>"></td>
                    </tr>
					<tr>
						<td><b>Tap Valves Setup:</b></td>
						<td><b>Valve Power Pin:</b><br/>The pin that powers the valves </td>
						<td><input type="text" name="valvesPowerPin" class="smallbox" value="<?php echo ($config[ConfigNames::ValvesPowerPin]) ?>"></td>
					</tr>
					<tr>
						<td><b>Tap Valves Setup:</b></td>
						<td><b>Valve On Time:</b><br/>The time the valves remain on </td>
						<td><input type="text" name="valvesOnTime" class="smallbox" value="<?php echo ($config[ConfigNames::ValvesOnTime]) ?>"></td>
					</tr>
	<?php } ?>
					<tr>
						<td><b>Tap Setup:</b></td>
						<td><b>Number Of Taps:</b><br/>The number of taps in the system</td> 
						<td><input type="text" name="numberOfTaps" class="smallbox" value="<?php echo $numberOfTaps ?>" /></td>
					</tr>
					<tr>
						<td colspan="3">
                        	<input type="submit" name="saveSettings" class="btn" value="Save" />
                            <input type="submit" name="revert"       class="btn" value="Revert" />
                        </td>
					</tr>
			</tbody>
		</table>
	</form>
    </div>
	<!-- End Tap Config Form -->
<br />
	<!-- Start On Tap Section -->
	<?php 
		$tapsErrorMsg = "";
		if( count($beerList) == 0 ){
			$tapsErrorMsg .= "At least 1 beer needs to be created, before you can assign a tap. <a href='beer_form.php'>Click here to create a beer</a><br/>";
		}
		if( count($kegList) == 0 ){
			$tapsErrorMsg .= "At least 1 keg needs to be created, before you can assign a tap. <a href='keg_form.php'>Click here to create a keg</a><br/>";
		}						

		if( strlen($tapsErrorMsg) > 0 ){ 
			echo $htmlHelper->CreateMessage('warning', $tapsErrorMsg);	
		}else{
?>	
	    <form method="POST" id="tap-form" onSubmit='return validateBeerSelected("kegId", "beerId")'>
                <input type="hidden" name="enableTap" id="enableTap" value="" />
                <input type="hidden" name="disableTap" id="disableTap" value="" />
        
                <?php foreach($activeTaps as $tap){ 
	                if(null == $tap)continue; 
				?>
                	<input type="hidden" name="tapId[]" value="<?php echo $tap->get_id(); ?>" />
                <?php } ?> 
    		<div id="messageDiv" class="error status" style="display:none;"><span id="messageSpan"></span></div>
			<table class="contentbox" style="width:75%; border:0;" >
            <thead>
                <tr>
                    <th>Tap<br>Description</th>
                    <th>Keg<br>(OnTap Number)</th>
                    <th style="width:10%">Beer</th>
                    <th>Start<br>Amount (Gal)</th>
                    <th>Current<br>Amount(Gal)</th>
                    <?php if($config[ConfigNames::UseFlowMeter]) { ?>
                        <th>Flow Pin</th>
                        <th>Count Per Gal</th>
                    <?php } ?>
                    <?php if($config[ConfigNames::UseTapValves]) { ?>
                        <th>Valve Pin</th>
                        <th> PI<br>Pin?</th>
                    	<th></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($activeTaps as $tap){ ?>
                    <?php if(null == $tap)continue; ?> 
                    <tr>
                    <?php 
                        $keg = null;
                        $beer = null;
                        if(null !== $tap->get_kegId())$keg = $kegManager->GetById($tap->get_kegId());
                        if(null !== $keg)$beer = $beerManager->GetById($keg->get_beerId());
                        if(null == $keg)unset($keg);
                        if(null == $beer)unset($beer);
                    ?>
                        <td>
                            <?php if(isset($tap) ) { ?>
                                <input type="text" id="tapNumber<?php echo $tap->get_id();?>" class="smallbox" name="tapNumber[]" value="<?php echo $tap->get_tapNumber(); ?>" />
                            <?php } ?>
                        </td>
                        <td>                            
							<?php 					
                                $str = "<select id='kegId".$tap->get_id()."' name='kegId[]' class='' onChange='toggleDisplay(this, \"kegId\", \"beerId\", ".$tap->get_id().")'>\n";
                                $str .= "<option value=''>Select One</option>\n";
                                foreach($kegList as $item){
                                    if( !$item ) continue;
                                    $sel = "";
                                    if( $tap && $tap->get_kegId() == $item->get_id() ) $sel .= "selected ";
                                    $desc = $item->get_id();
                                    if($item->get_label() && $item->get_label() != "" && $item->get_label() != $item->get_id())$desc.="-".$item->get_label();
									if($item->get_onTapId() && $item->get_onTapId() != "")$desc.="(".$item->get_tapNumber().")";
                                    $str .= "<option value='".$item->get_id()."~".$item->get_beerId()."~".$item->get_ontapId()."' ".$sel.">".$desc."</option>\n";
                                }					
                                $str .= "</select>\n";
                                                        
                                echo $str;
                            ?>
                        </td> 
                        <td style="width:5%">	
                            <?php 
								$selectedBeer = "";
								if( isset($tap) && isset($beer) ) $selectedBeer = $beer->get_id() ;
								echo $htmlHelper->ToSelectList("beerId[]", "beerId".$tap->get_id(), $beerList, "name", "id", $selectedBeer, "Select One"); 
							?>
                        </td>             
                        <td>
							<input type="text" id="startAmount<?php echo $tap->get_id();?>" class="smallbox" name="startAmount[]" value="<?php echo $tap->get_startAmount() ?>" />
                        </td>
                    	<td>
							<input type="text" id="currentAmount<?php echo $tap->get_id();?>" class="smallbox" name="currentAmount[]" value="<?php echo $tap->get_currentAmount() ?>" />
                        </td>               
                        <?php if($config[ConfigNames::UseFlowMeter]) { ?>
                                <td>
                                    <?php if( isset($tap) ) { ?>
                                        <input type="text" id="flowpin<?php echo $tap->get_id();?>" class="smallbox" name="flowpin[]" value="<?php echo $tap->get_flowPinId(); ?>" />
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if( isset($tap) ) { ?>
                                        <input type="text" id="countpergallon<?php echo $tap->get_id();?>" class="smallbox" name="countpergallon[]" value="<?php echo $tap->get_count(); ?>" />
                                    <?php } ?>
                                </td>
                        <?php } ?>
                        <?php if($config[ConfigNames::UseTapValves]) { ?>
                            <td>
                                <?php if( isset($tap) ) { ?>
                                    <input type="text" id="valvepin<?php echo $tap->get_id();?>" class="smallbox" name="valvepin[]" value="<?php echo abs($tap->get_valvePinId()); ?>" />
                                <?php } ?>
                            </td>
                            <td>
                                <?php if( isset($tap) ) { ?>
                                	<input type="checkbox" id="valvepinPi<?php echo $tap->get_id();?>" class="xsmallbox" name="valvepinPi[<?php echo $tap->get_id();?>]" value="1" <?php if($tap->get_valvePinId() < 0)echo "checked" ?>  />
                                <?php } ?>
                            </td>
                        <?php } ?>
          				<?php 
                            if($config[ConfigNames::UseTapValves]) {
                                $kegOn = "";
                                $kegOnSay = "";
                                if ( $tap->get_valveOn() < 1 ) {
                                    $kegOn = "enableTap";
                                    $kegOnSay = "Let it flow";
                                } else {
                                    $kegOn = "disableTap";
                                    $kegOnSay = "Stop this";
                                }
                            ?>
                            <td>
                                <?php if( isset($tap) ) { ?>
                                    <button name="<?php echo $kegOn?>" type="button" class="btn" style="white-space:nowrap" value="<?php echo $tap->get_id()?>" onClick="document.getElementById('<?php echo $kegOn?>').value=this.value;this.form.submit();"><?php echo $kegOnSay?></button>
                                <?php } ?>
                            </td>
                        <?php } ?>
<!--                        <td>
                            <input name="kickKeg" type="submit" class="btn" value="Kick Keg" />
                        </td>-->
                </tr>					
                <?php } ?>
            </tbody>
        </table>
            <input name="saveTapConfig" type="submit" class="btn" value="Save" />
            <input type="submit" name="revert"        class="btn" value="Revert" />
        </form>	
        <br />
        <div>
            &nbsp; &nbsp; 
        </div>
    <?php } ?>
	</div>
	<!-- End On Tap Section -->
	<!-- Start Footer -->   
<?php
include 'footer.php';
?>
	<!-- End Footer -->
	</div>
	<!-- Right Side/Main Content End -->
	<!-- Start Left Bar Menu -->   
<?php
include 'left_bar.php';
?>
	<!-- End Left Bar Menu -->  
	<!-- Start Js  -->
<?php
include 'scripts.php';
?>

<script>

	function validateBeerSelected(kegSelectStart, beerSelectStart) {
		var ii = 1;
		var kegSelect = null;
		while( (kegSelect = document.getElementById(kegSelectStart+ii)) != null){
			if(kegSelect.selectedIndex != 0){
				if(document.getElementById(beerSelectStart+ii).selectedIndex == 0) {
					var msgDiv = document.getElementById("messageDiv");
					if(msgDiv != null)msgDiv.style.display = "";
					var msgSpan = document.getElementById("messageSpan");
					var kegSelArr = kegSelect.value.split("~");
					if(msgSpan != null) msgSpan.innerHTML = "Tap "+ii+" Keg "+kegSelArr[0]+" needs to have a beer associated to it or not associated to a tap"
					return false;
				}
			}	 
			ii++;
		}
	    return true; 
	 } 
	 
	$(function() {		
		$('#tap-form').validate({
		rules: {		
			<?php 
			$comma = "";
			foreach($activeTaps as $tap){ 
				if(null == $tap)continue; 
			?>
				<?php echo $comma; ?>tapId<?php echo $tap->get_id(); ?>: { required: true }
				<?php $comma = ","; ?>
				<?php echo $comma; ?>kegId<?php echo $tap->get_id(); ?>: { required: true }
				<?php echo $comma; ?>startAmount<?php echo $tap->get_id(); ?>: { required: true, number: true }
				<?php echo $comma; ?>currentAmount<?php echo $tap->get_id(); ?>: { required: true, number: true }
			<?php } ?> 
				//,tapId: { required: true }				
				//,kegId: { required: true, beerRequired: true }
				//,startAmount: { required: true, number: true }
				//,currentAmount: { required: true, number: true }
			}
		});		
	});
	function toggleSettings(callingAnchor, settingsDiv) {
		var div = document.getElementById(settingsDiv);
		if(div != null){
			if(div.style.display == ""){
				div.style.display = "none";
				callingAnchor.style.background = "url(img/bg_navigation.png) no-repeat top;";				
			}else{
				div.style.display = "";
				callingAnchor.style.background = "url(img/bg_navigation.png) 0 -76px;";				
			}
			if(document.getElementById("settingsExpanded")!= null)document.getElementById("settingsExpanded").value = div.style.display;
		}
	}
	
	function toggleDisplay(selectObject, kegSelectStart, secSelectBeerStart, tapId) {
		var msgDiv = document.getElementById("messageDiv");
		if(msgDiv != null) msgDiv.style.display = "none"
		var display = true;
		var kegSelArr = selectObject.value.split("~");
		//Select array is kegid~beerid(in keg)~tapId(keg is on)
		var beerId = null;
		if(kegSelArr.length > 1 && kegSelArr[1] != "")
		{
			beerId = kegSelArr[1];
		}
		else
		{
			var secSelect = document.getElementById(secSelectBeerStart+tapId);
			secSelect.selectedIndex = 0;
		}
		if(kegSelArr.length > 2 && kegSelArr[2] != "") 
		{
			var onOtherTap = null;
			var ii = 1;
			var secOtherTapKegSelect = null;
			while( (secOtherTapKegSelect = document.getElementById(kegSelectStart+ii++)) != null){
				if(ii-1 == tapId)continue;
				otherKegSelArr = secOtherTapKegSelect.value.split("~");
				if(otherKegSelArr[0] == kegSelArr[0]) onOtherTap = kegSelArr[2];
				if(onOtherTap)
				{
					while(3 > kegSelArr.length)kegSelArr.push(null);
					kegSelArr[2] = otherKegSelArr[2];
				 	break;
				}
			}
			if(onOtherTap){
				var secOtherTapBeerSelect = document.getElementById(secSelectBeerStart+onOtherTap);
				if(msgDiv != null)msgDiv.style.display = "";
				var msgSpan = document.getElementById("messageSpan");
				if(msgSpan != null) msgSpan.innerHTML = "Keg "+kegSelArr[0]+" currently on Tap "+kegSelArr[2]+" and will be moved to tap <?php echo ($tap?$tap->get_id():'');?> and updated to current selected beer"
				if(secOtherTapBeerSelect != null)secOtherTapBeerSelect.selectedIndex = 0;
				if(secOtherTapKegSelect != null)secOtherTapKegSelect.selectedIndex = 0;		
			}	
		}
		if(beerId != null)
		{
			var secSelect = document.getElementById(secSelectBeerStart+tapId);
			var secSelectOptions = secSelect.options;
			for (var i = 0; i < secSelectOptions.length; i++) 
			{
				if (secSelectOptions[i].value == beerId) {
					secSelect.selectedIndex = i;
					break;
				}
			}
		}
	}
</script>
	<!-- End Js -->

</body>

</html>