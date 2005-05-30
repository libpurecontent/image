<?php

# Ensure the pureContent framework is loaded and clean server globals
require_once ('pureContent.php');


# Define a class containing image-related static methods
class image
{
	# Wrapper function to create a photo gallery
	function gallery ($directory = './', $imageGenerationScript = 'image.html', $width = 200, $showAttributes = true, $displayImmediately = true)
	{
		# Load the directory support library
		require_once ('directories.php');
		
		# Parse the specified directory so that it is always the directory from the server root
		$directory = directories::parse ($directory);
		
		# Define the supported extensions
		$supportedFileTypes = array (/*'gif', */'jpg', 'jpeg', 'png');
		
		# Read the directory, including only supported file types (i.e. extensions)
		$files = directories::listFiles ($directory, $supportedFileTypes);
		
		# Show a message if there are no files in the directory and exit the function
		if (count ($files) < 1) {
			$html ='<p>There are no images to view in this location.</p>';
			if ($displayImmediately) {echo $html;}
			return $html;
		}
		
		# Start the HTML block
		$startHtml = "\n\t" . '<div class="gallery">';
		if ($displayImmediately) {echo $startHtml;}
		
		# Loop through each file and construct the HTML
		$compiledHtml = '';
		foreach ($files as $file => $attributes) {
			
			# Define the link
			$link = $file;
			
			# Define the HTML
			$html = "\n" . '
			<div class="image">
				<a href="' . $link . '" target="_blank"><img src="' . $imageGenerationScript . '?' . $width . ',' . str_replace (' ', '%20', $directory) . str_replace (' ', '%20', $file) . '" width="' . $width . '" alt="[Click for full-size image; opens in a new window]" /></a>
				' . (($showAttributes) ? '<p><strong>' . $file . '</strong> [' . round ($attributes['size'] / '1024', -1) . ' KB]</p>' : '')
				. '<p>' . strftime ('%a %d/%b/%Y, %I:%M%p', $attributes['time']) . '</p>
			</div>';
			
			# If necessary, display the HTML immediately
			if ($displayImmediately) {echo $html;}
			
			# Also compile the HTML
			$compiledHtml .= $html;
		}
		
		# End the HTML
		$endHtml = "\n\n\t" . '</div>' . "\n";
		if ($displayImmediately) {echo $endHtml;}
		
		# Return the compiled HTML in case that is needed
		return $startHtml . $compiledHtml . $endHtml;
	}
	
	
	# Function to provide a gallery with comments underneath
	function commentGallery ($comments = array (), $smallVersionDirectory = 'thumbnails/', $filetype = '.jpg', $maxWidth = 400)
	{
		# Load the directory support library
		require_once ('directories.php');
		
		# Get all files in the current directory, ensuring that the REQUEST_URI ends with a filename so that dirname works properly
		$directory = dirname ($_SERVER['REQUEST_URI'] . ((substr ($_SERVER['REQUEST_URI'], -1) == '/') ? 'index.html' : ''));
		
		# Ensure the directory ends with a slash
		if (substr ($directory, -1) != '/') {$directory .= '/';}
		
		# Define the supported extensions
		$supportedFileTypes = array (/*'gif', */'jpg', 'jpeg', 'png');
		
		# Read the directory, including only supported file types (i.e. extensions)
		$files = directories::listFiles ($directory, $supportedFileTypes);
		
		# Sort the keys, enabling e.g. 030405b.jpg to come before 030405aa.jpg
		uksort ($files, array ('image', 'imageNameSort'));
		
		# Show a message if there are no files in the directory and exit the function
		if (count ($files) < 1) {
			$html = '<p>There are no images to view in this location.</p>';
			return $html;
		}
		
		# Start the HTML block
		$startHtml = "\n\t" . '<div class="gallery">';
		
		# Loop through each file and construct the HTML
		$compiledHtml = '';
		foreach ($files as $file => $attributes) {
			
			# Define the location and ensure the file exists
			$location = './' . $smallVersionDirectory . $file;
			if (!file_exists ($location)) {
				echo "\n<p>Error: image $file not found.</p>";
				continue;
			}
			
			# Get the image size
			list ($width, $height, $type, $imageSize) = getimagesize ($location);
			
			# Determine whether there is a comment (no array index or empty comment)
			$isComment = (isSet ($comments[$file]) ? (!empty ($comments[$file]) ? true : false) : false);
			
			# Define the HTML
			$compiledHtml .= "\n" . '
			<div class="image" id="#image' . $attributes['name'] . '">
				<a href="' . $file . '" target="_blank"><img src="' . $smallVersionDirectory . $file . '" ' . $imageSize . ' alt="Photograph" /></a>
				<p>' . ($isComment ? $comments[$file] : '&nbsp;') . '</p>
			</div>';
		}
		
		# End the HTML
		$endHtml = "\n\n\t" . '</div>' . "\n";
		
		# Return the compiled HTML in case that is needed
		return $startHtml . $compiledHtml . $endHtml;
	}
	
	
	# Helper function to sort by key length
	function imageNameSort ($a, $b)
	{
		# If they are the same, return 0 [This should never arise]
		if ($a == $b) {return 0;}
		
		# Validate and obtain matches for a pattern of the (i) 6-digit reverse-date (ii) letter(s) and (iii) [discarded] file extension
		if ((!eregi ('([0-9]{6})([a-z]+).(gif|jpg|jpeg|png)', $a, $matchesA)) || (!eregi ('([0-9]{6})([a-z]+).(gif|jpg|jpeg|png)', $b, $matchesB))) {
			return NULL;
		}
		
		# Compare the numeric portion
		if ($matchesA[1] < $matchesB[1]) {return -1;}
		if ($matchesA[1] > $matchesB[1]) {return 1;}
		
		# Compare string length
		if (strlen ($matchesA[2]) < strlen ($matchesB[2])) {return -1;}
		if (strlen ($matchesA[2]) > strlen ($matchesB[2])) {return 1;}
		
		# Otherwise compare the strings
		return strcmp ($matchesA[2], $matchesB[2]);
	}
	
	
	# Function to resize an image; supported input and output formats are: jpg, png
	function resize ($sourceFile, $outputFormat = 'jpg', $newWidth = '', $newHeight = '')
	{
		# Decode the $sourceFile to remove HTML entities
		$sourceFile = str_replace ('//', '/', $_SERVER['DOCUMENT_ROOT'] . str_replace ('%20', ' ', $sourceFile));
		
		# Check that the file exists for security reasons
		if (!file_exists ($sourceFile)) {echo '<p>Error: the selected file could not be found.</p>'; return false;}
		
		# Obtain the input format by taking the file extension, allowing for .jpeg and .jpg for JPG format
		$inputFileExtension = substr ($sourceFile, -4);
		if (substr ($sourceFile, -5) == '.jpeg') {$inputFileExtension = '.jpg';}
		
		# Obtain the source image
		switch (strtolower ($inputFileExtension)) {
				
			/* # GIF format
			case '.gif':
				$sourceFile = ImageCreateFromGIF ($sourceFile);
				break; */
				
			# JPG format
			case '.jpg':
				$sourceFile = ImageCreateFromJPEG ($sourceFile);
				break;
				
			# PNG format
			case '.png':
				$sourceFile = ImageCreateFromPNG ($sourceFile);
				break;
				
			# If an invalid format has been requested, return false
			default:
				 echo '<p>Error: an unsupported input format was requested.</p>';
				 return false;
		}
		
		# Obtain the height and width
		$originalWidth = ImageSx ($sourceFile);
		$originalHeight = ImageSy ($sourceFile);
		
		# Ensure that a valid width and height have been entered
		if (!is_numeric ($newWidth) && !is_numeric ($newHeight)) {
			$newWidth = $originalWidth;
			$newHeight = $originalHeight;
		}
		
		# Assign the width and height, proportionally if necessary
		$newWidth = (is_numeric ($newWidth) ? $newWidth : (($newHeight / $originalHeight) * $originalWidth));
		$newHeight = (is_numeric ($newHeight) ? $newHeight : (($newWidth / $originalWidth) * $originalHeight));
		
		# Create the resized image
		$output = ImageCreateTrueColor ($newWidth, $newHeight);
		ImageCopyResampled ($output, $sourceFile, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
		
		# Send the image
		switch ($outputFormat) {
				
			/* # GIF format
			case 'gif':
				header ("Content-Type: image/gif");
				ImageGIF ($output);
				break; */
				
			# JPG format
			case 'jpg':
			case 'jpeg':
				header ("Content-Type: image/jpg");
				ImageJPEG ($output);
				break;
				
			# PNG format
			case 'png':
				header ("Content-Type: image/png");
				ImagePNG ($output);
				break;
				
			# If an invalid format has been requested, return false
			default:
				 echo '<p>Error: an unsupported output format was requested.</p>';
				 return false;
		}
	}
	
	
	# Function to display a gallery of files
	function switchableGallery ()
	{
		# Get a listing of files in the current directory (this assumes the current page is called index.html)
		require_once ('directories.php');
		$rawFiles = directories::listFiles ($_SERVER['PHP_SELF']);
		
		# Loop through the list of files
		foreach ($rawFiles as $file => $attributes) {
			
			# List only jpeg files by creating a new array of weeded files
			if ($attributes['extension'] == 'jpg') {
				$files[$file] = $attributes;
			}
		}
		
		# Sort the files alphabetically
		asort ($files);
		
		# Start a variable to hold the HTML
		$html = '';
		
		# Count the number of files and proceed only if there are any
		$totalFiles = count ($files);
		if ($totalFiles > 0) {
			
			# Begin the HTML list
			$jumplist = "\n" . '<p class="jumplist">Go to page:';
			
			# Loop through each file
			$i = 0;
			foreach ($files as $file => $attributes) {
				
				# If the file is the first, store it in memory in case it is needed
				if ($i == 0) {$firstFile[$file] = $attributes;}
				
				# Advance the counter
				$i++;
				
				# Pick out the currently selected image, based on the query string (if any) and assign a CSS flag
				#!# Somehow here need to add class="selected" to the first item when there is no query string or $i is 1?
				$selected = '';
				if ($attributes['name'] == $_SERVER['QUERY_STRING']) {
					$showFile[$file] = $attributes;
					$selected = ' class="selected"';
				} else if ($i == 1) {
					$selected = ' class="first"';
				}
				
				# Add the file to the jumplist of files
				$jumplist .= " <a href=\"?{$attributes['name']}\"$selected>{$attributes['name']}</a>";
			}
			
			# End the HTML link list
			$jumplist .= '</p>';
			
			# Add in the HTML link list
			$html .= $jumplist;
			
			# If no query string was given, or the query string does not match any file, select the first file as the one to be shown
			if (!isSet ($showFile)) {
				$showFile = $firstFile;
			}
			
			# Get the filename
			foreach ($showFile as $name => $attributes) {
				break;
			}
			
			# Get the image size
			list ($width, $height, $type, $attributes) = getimagesize ($_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF'] . $name);
			
			# Show the image
			$html .= "\n\n<img src=\"$name\" $attributes alt=\"Page {$attributes['name']}\" />";
			
			# Add in the HTML link list again
			$html .= $jumplist;
		}
		
		# Return the HTML
		return $html;
	}
}

?>