<?php
	error_reporting(E_ERROR | E_PARSE);
	
	if(!function_exists("array_column"))
	{
	    /**
	     * Retourne les valeurs d'une colonne d'un tableau d'entrée
	     *
	     * @param array  $array      Un tableau multi-dimensionnel depuis lequel la colonne de valeurs sera prélevée
	     * @param string $column_key La colonne de valeurs à retourner
	     *
	     * @return array
	     * @return false, si la clé n'est pas dans le tableau
	     */
	    function array_column($array, $column_name)
	    {
	        if(empty($array))
	            return false;
	
            return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
	    }
	}
	
	
	function show_status($done, $total, $size=30) {
	
	    static $start_time;
	
	    // if we go over our bound, just ignore it
	    if($done > $total) return;
	
	    if(empty($start_time)) $start_time=time();
	    $now = time();
	
	    $perc=(double)($done/$total);
	
	    $bar=floor($perc*$size);
	
	    $status_bar="\r[";
	    $status_bar.=str_repeat("=", $bar);
	    if($bar<$size){
	        $status_bar.=">";
	        $status_bar.=str_repeat(" ", $size-$bar);
	    } else {
	        $status_bar.="=";
	    }
	
	    $disp=number_format($perc*100, 0);
	
	    $status_bar.="] $disp%  $done/$total";
	
	    if($done == 0)
	        $rate = 0;
	    else
	       $rate = ($now-$start_time)/$done;
	    $left = $total - $done;
	    $eta = round($rate * $left, 2);
	
	    $elapsed = $now - $start_time;
	
	    $status_bar.= " remaining: ".number_format($eta)." sec.  elapsed: ".number_format($elapsed)." sec.";
	
	    echo "$status_bar  ";
	
	    flush();
	
	    // when done, send a newline
	    if($done == $total) {
	        echo "\n";
	    }
	
	}
	
	    // Chemin vers ton fichier
    	$vsg = 'vsg-evi.txt';

    	$filePath = realpath(dirname(__FILE__))."/".$vsg;
    	
        $txt_file    = file_get_contents($filePath);
        $rows        = explode("\n", $txt_file);
        array_shift($rows);
        
        
        // On récupère dans $policyOrder l'ordre dans lequel les règles doivent être rangées.
        // Cette variable nous sert en quelques sortes de référentiel
        $policyOrder     = array();
        $isPrevLineGood  = false;
        
        foreach($rows as $row => $data)
        {
            $line = trim($data);
            $row_data = explode(' ', $line);

            if($isPrevLineGood == true && $row_data[0] != "rule")
                $isPrevLineGood = false;

            if($row_data[0] == "Policy")
                if($row_data[1] != "default@root")
                    $isPrevLineGood = true;      
            
            if( $row_data[0] == "rule" && $isPrevLineGood == true) 
                array_push($policyOrder, $row_data[1]);
        }        
        

        // On recommence la même manipulation, cette fois pour récupérer le contenu des règles
        $txt_file    = file_get_contents($filePath);
        $rows        = explode("\n", $txt_file);
        array_shift($rows);
        
        
        // On récupère dans $policyOrder l'ordre dans lequel les règles doivent être rangées.
        // Cette variable nous sert en quelques sortes de référentiel
        $policies         = array();
        $tempPolicyname   = "";
        $isMatchedRule    = false;
        $isDst_attributes = false;
        $isSrc_attributes = false;
        $isServices       = false;
        $isAction         = false;
        
        $destination = '"';
        $source      = '"';
        $port        = '"';
        $protocol    = '"';
        
        $policy = array();
        
        foreach($rows as $row => $data)
        {
            $line = trim($data);
            $row_data = explode(' ', $line);
        
            if($row_data[0] == "rule")
            {
                $tempPolicyname = $row_data[1];
                $key = array_search($tempPolicyname, $policyOrder);
                if($key != false)
                {
                    $isMatchedRule = true;
                    $policy["NAME"] = '"'.$tempPolicyname.'"'; 
                }
            }
    
            if( $isMatchedRule )
            {
                $end = $row_data[0].' '.$row_data[1];
                
                if($isDst_attributes)
                {
                    if($row_data[3] == "eq")
                        $destination .= $row_data[4]."\n";
                    if($row_data[3] == "in-range")
                        $destination .= "[".$row_data[4]."-".$row_data[5]."]\n";
                    if($row_data[3] == "prefix")
                        $destination .= $row_data[4]."/".$row_data[5]."\n";
                }
                
                if($isSrc_attributes)
                {
                    if($row_data[3] == "eq")
                        $source .= $row_data[4]."\n";
                    if($row_data[3] == "in-range")
                        $source .= '['.$row_data[4].'-'.$row_data[5]."]\n";
                    if($row_data[3] == "prefix")
                        $source .= $row_data[4]."/".$row_data[5]."\n";
                }
                
                if($isServices)
                {
                    if($row_data[5] != "")
                        $protocol .= $row_data[5]."\n";
                    
                    if($row_data[7] != "")
                        $port .= $row_data[7]."\n";
                    
                }
                
                
                if($row_data[0] == "dst-attributes")
                    $isDst_attributes = true;
                
                if($row_data[0] == "src-attributes")
                {
                    $isSrc_attributes = true;
                    $isDst_attributes = false;
                    $destination = substr($destination, 0, -2);
                    $policy["DESTINATION"] = $destination.'"';
                    $destination = '"';
                }
            
                if($row_data[0] == "service/protocol-attribute")
                {
                    $isServices = true;
                    $isSrc_attributes = false;
                    $source = substr($source, 0, -2);
                    $policy["SOURCE"] = $source.'"';
                    $source = '"';
                }
            
                if($row_data[0] == "action" && $isServices)
                {
                    $isServices = false;
                
                    $port = substr($port, 0, -2);
                    $policy["PROTOCOL"] = $protocol;
                    $policy["PORT"] = $port.'"';
                    $protocol = '"';
                    $port ='"';
            
                    $policy["ACTION"] = '"'.$row_data[1].'"';
                }
                
                if($end == "action log")
                {
                    $isMatchedRule = false;
                    //echo "policy in action log ".$key."\n";
                    //error_log(print_r($policy, true));
                    $policies[$key] = $policy;
                    $policy = array();
                    continue;
                }
            }
        }
        
        $fp  = fopen(realpath(dirname(__FILE__)).'/Output-vsg-evi.csv', 'w+');
        fwrite($fp, "\"Rule\";\"Source\";\"Destination\";\"Protocol\";\"Port\";\"Action\"\r\n");
        
        foreach ($policies as $index => $policy)
        {
            if(count($policy) != 6)
                continue;
            
            if($policy["SOURCE"] == "")
                $policy["SOURCE"] = '"any"';
            
            if($policy["DESTINATION"] == "")
                $policy["DESTINATION"] = '"any"';
			if($policy["PROTOCOL"] == "")
                $policy["PROTOCOL"] = '"any"';
			if($policy["PORT"] == "")
                $policy["PORT"] = '"any"';
            
            $tmp = array_unique(explode('"', $policy["PROTOCOL"]));
            foreach ($tmp as $proto)
                $policy["PROTOCOL"] .= $proto."\n";
            
            $policy["PROTOCOL"] = substr($policy["PROTOCOL"], 0, -2);
            $policy["PROTOCOL"] = $policy["PROTOCOL"].'"';
            fwrite($fp, $policy["NAME"].";".$policy["SOURCE"].";".$policy["DESTINATION"].";".$policy["PROTOCOL"].";".$policy["PORT"].";".$policy["ACTION"]."\r\n");
        }
        fclose($fp);
	exit(0);
?>