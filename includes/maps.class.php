<?php
	class GoogleMaps
	{
		public function to_maps($msg)
		{
			if(stripos($msg, '[Location=') !== false)
			{
				$rep = str_replace('[Location=', '', $msg);
				$coordenate = str_replace(']', '', $rep);
				
				$find = array(',', '.', '-', ' ');
				$change = array('', '', '', '');
				
				$custom_id = 'l_'.str_replace($find, $change, $coordenate);
				
				$params = $custom_id.','.$coordenate;
				
				$result = '<div class="map-embed" id="'.$custom_id.'" rel="'.$coordenate.'"></div>';
				$result .= 
				'
					<script type="text/javascript">
						google_maps('.$params.');
					</script>
				';
				
				return $result;
			} else {
				return $msg;	
			}
		}
	}
	
	$maps = new GoogleMaps();
	
?>