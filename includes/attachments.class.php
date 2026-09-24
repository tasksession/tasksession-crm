<?php
	class Attachment
	{
		private $image_prefix = 'photoAttachment-';
		private $file_prefix = 'fileAttachment-';
		public $urlPath = '';
		public $filePath = '';
		
		private function attach($prefix, $attachment)
		{
			$search = '['.$prefix;
			
			$rep_1 = str_replace($search, '', $attachment);
			$rep_2 = str_replace(']', '', $rep_1);
			
			return $rep_2;
		}
		
		public function attached_image($attachement)
		{
			$file = $this->attach($this->image_prefix, $attachement);	
			if(file_exists($this->filePath.$file))
			{
				$extension = pathinfo($file, PATHINFO_EXTENSION);
				if($extension == 'png' || $extension == 'gif' || $extension == 'jpeg' || $extension == 'jpg'){
				return '<a href="'.$this->urlPath.$file.'" data-lightbox="img-'.time().'"><img class="img-fluid" src="includes/thumbnail.php?src='.$this->urlPath.$file.'&h=120&w=120" alt="Image" /></a>';	
				} else {

				switch($extension)
				{
					case "txt":
						$result = '
						<img src="assets/images/files/file-types/text.png" alt="[text file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "xls":
					case "xlsx":
						$result = '
						<img src="assets/images/files/file-types/excel.png" alt="[excel file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "doc":
					case "docx":
						$result = '
						<img src="assets/images/files/file-types/word.png" alt="[word file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "ppt":
					case "pptx":
						$result = '
						<img src="assets/images/files/file-types/powerpoint.png" alt="[powerpoint file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "pdf":
						$result = '
						<img src="assets/images/files/file-types/pdf.png" alt="[pdf file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "eps":
						$result = '
						<img src="assets/images/files/file-types/illustrator.png" alt="[eps file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "psd":
						$result = '
						<img src="assets/images/files/file-types/photoshop.png" alt="[psd file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "zip":
						$result = '
						<img src="assets/images/files/file-types/zip.png" alt="[zip file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a target="_blank" href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					default:
						$result = '<p class="alert alert-info">This file does not exist anymore</p>';
					break;
				}

	
				return $result;
				}
			} else {
				return '<p class="alert alert-info">'.$base_url.'This file does not exist anymore</p>';	
			}
		}
		
		public function attached_file($attachement)
		{
			$file = $this->attach($this->file_prefix, $attachement);
			
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			
			if(file_exists($this->filePath.$file))
			{
				switch($extension)
				{
					case "txt":
						$result = '
						<img src="assets/images/files/file-types/text.png" alt="[text file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "xls":
					case "xlsx":
						$result = '
						<img src="assets/images/files/file-types/excel.png" alt="[excel file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "doc":
					case "docx":
						$result = '
						<img src="assets/images/files/file-types/word.png" alt="[word file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "ppt":
					case "pptx":
						$result = '
						<img src="assets/images/files/file-types/powerpoint.png" alt="[powerpoint file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "pdf":
						$result = '
						<img src="assets/images/files/file-types/pdf.png" alt="[pdf file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "eps":
						$result = '
						<img src="assets/images/files/file-types/illustrator.png" alt="[eps file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "psd":
						$result = '
						<img src="assets/images/files/file-types/photoshop.png" alt="[psd file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					case "zip":
						$result = '
						<img src="assets/images/files/file-types/zip.png" alt="[zip file attachment]" />
						<p style="width: 128px; margin: 0;" align="center"><a href="'.$this->urlPath.$file.'">'.$file.'</a></p>
						';
					break;
					
					default:
						$result = '<p class="alert alert-info">This file does not exist anymore</p>';
					break;
				}
			} else {
				$result = '<p class="alert alert-info">This file does not exist anymore</p>';	
			}
			
			return $result;
		}
		
		public function attachments($attachment)
		{
			if(stripos($attachment, $this->image_prefix) !== false)
			{
				return $this->attached_image($attachment);
			} elseif(stripos($attachment, $this->file_prefix)) {
				return $this->attached_file($attachment);	
			}
			
			return $attachment;
		}
			
	}
	
	$attach = new Attachment();
	$attach->urlPath = rtrim($base_url, '/').'/uploads/user-uploads/';
$attach->filePath = $base_root.'/uploads/user-uploads/';
?>