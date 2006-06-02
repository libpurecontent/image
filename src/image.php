<?php

# Ensure the pureContent framework is loaded and clean server globals
require_once ('pureContent.php');


# Define a class containing image-related static methods
class image
{
	# Function to get a list of images in a directory
	function getImageList ($directory)
	{
		# Load the directory support library
		require_once ('directories.php');
		
		# Clean the supplied directory
		$directory = urldecode ($directory);
		
		# Parse the specified directory so that it is always the directory from the server root
		$directory = directories::parse ($directory);
		
		# Define the supported extensions
		$supportedFileTypes = array (/*'gif', */'jpg', 'jpeg', 'png');
		
		# Read the directory, including only supported file types (i.e. extensions)
		$files = directories::listFiles ($directory, $supportedFileTypes);
		
		# Return the list
		return $files;
	}
	
	
	# Function to provide a gallery with comments underneath
	function gallery ($captions = array (), $thumbnailsDirectory = 'thumbnails/', $size = 400, $imageGenerator = '/images/generator', $orderByCaptionOrder = false, $exclude = array ())
	{
		# Allow the script to take longer to run (particularly the first time)
		ini_set ('max_execution_time', 120);
		
//		if ($orderByCaptionOrder) {echo $orderByCaptionOrder;}
		
		# Define the current directory, ensuring it ends with a slash and ensuring that spaces are converted
		$directory = dirname ($_SERVER['REQUEST_URI'] . ((substr ($_SERVER['REQUEST_URI'], -1) == '/') ? 'index.html' : ''));
		if (substr ($directory, -1) != '/') {$directory .= '/';}
		$directory = str_replace ('%20', ' ', $directory);
		
		# If there is a (relative) thumbnail directory, prepend the current directly onto it
		if (substr ($thumbnailsDirectory, 0, 1) != '/') {
			$thumbnailsDirectory = $directory . $thumbnailsDirectory;
		}
		
		# Get the list of images in the directory
		$files = self::getImageList ($directory);
		
		# Show a message if there are no files in the directory and exit the function
		if (!$files) {
			return $html = '<p>There are no images to view in this location.</p>';
		}
		
		# Sort the keys, enabling e.g. 030405b.jpg to come before 030405aa.jpg
		uksort ($files, array ('image', 'imageNameSort'));
		
		# Start the HTML block
		$html = "\n\t" . '<div class="gallery">';
		
		# Ensure the thumbnail directory exists if one is required (if not, thumbnails are dynamic and not cached)
		if ($thumbnailsDirectory) {
			$thumbnailServerDirectory = $_SERVER['DOCUMENT_ROOT'] . $thumbnailsDirectory;
			if (!is_dir ($thumbnailServerDirectory) && is_writable ($_SERVER['DOCUMENT_ROOT'] . $directory)) {
				mkdir ($thumbnailServerDirectory, 0777);
			}
		}
		if (!is_dir ($thumbnailServerDirectory)) {$thumbnailsDirectory = false;}
		
		# Loop through each file and construct the HTML
		foreach ($files as $file => $attributes) {
			
			# Skip if excluded
			if (in_array ($file, $exclude)) {continue;}
			
			# Use/create physical thumbnails if required
			if ($thumbnailsDirectory) {
				
				# If there is no thumbnail, make one
				if (!file_exists ($_SERVER['DOCUMENT_ROOT'] . $thumbnailsDirectory . $file)) {
					
					# If there is no image resizing support, say so
					if (!extension_loaded ('gd') || !function_exists ('gd_info')) {
						return $html = '<p class="warning">Error: This server does not appear to support GD2 image resizing and so thumbnails must be created manually.</p>';
					}
					
					# Determine the image location
					$imageLocation = $directory . $file;
					
					# Get the size of the main image
					list ($width, $height) = self::scale ($_SERVER['DOCUMENT_ROOT'] . $imageLocation, $size);
					
					# Attempt to resize; if this fails, do not add the image to the gallery
					if (!self::resize ($imageLocation, $attributes['extension'], $width, $height, $thumbnailsDirectory . $file)) {
						continue;
					}
				}
				
				# Get the image size
				list ($width, $height, $type, $imageSize) = getimagesize ($_SERVER['DOCUMENT_ROOT'] . $thumbnailsDirectory . $file);
				
				# Define the link
				$link = '<a href="' . rawurlencode ($file) . '" target="_blank" rel="lightbox[group]"><img src="' . str_replace (' ', '%20', $thumbnailsDirectory) . rawurlencode ($file) . '" ' . $imageSize . ' alt="Photograph" /></a>';
			} else {
				
				# Get the width of the new image
				list ($width, $height) = self::scale ($_SERVER['DOCUMENT_ROOT'] . $directory . $file, $size);
				
				# Define the link
				$link = '<a href="' . rawurlencode ($file) . '" target="_blank" rel="lightbox[group]"><img src="' . $imageGenerator . '?' . $width . ',' . str_replace (' ', '%20', $directory) . rawurlencode ($file) . '" width="' . $width . '" alt="[Click for full-size image; opens in a new window]" /></a>';
			}
			
			# Define the caption
			if ($captions === true) {
				$caption = '<strong>' . $file . '</strong> [' . round ($attributes['size'] / '1024', -1) . ' KB]<br />' . strftime ('%a %d/%b/%Y, %l:%M%p', $attributes['time']);
			} else {
				# Set the caption if a comment exists
				$caption = (isSet ($captions[$file]) ? $captions[$file] : '&nbsp;');
			}
			
			# Define the HTML
			#!# Find a more generic way of making id attributes safe
			$html .= "\n" . '
			<div class="image" id="image' . str_replace (array (' ', '+', "'", ), '__', $attributes['name']) . '">
				' . $link . '
				<p>' . $caption . '</p>
			</div>';
		}
		
		# End the HTML
		$html .= "\n\n\t</div>\n";
		
		# Return the compiled HTML in case that is needed
		return $html;
	}
	
	
	# Function to surround an image with an HTML page
	/* # Use mod_rewrite with something like:
	   # RewriteEngine On
	   # RewriteRule ^/locationOfPagesAndImages/([0-9]+).([0-9]+).html$ /images/pagemaker.html?image=$1.$2.png [passthrough]
	*/
	function pagemaker ()
	{
		# Get the image
		$image = (isSet ($_GET['image']) ? $_GET['image'] : '');
		
		# Get root
		$root = (isSet ($_GET['root']) ? $_GET['root'] : '');
		$root = ((substr ($root, -1) == '/') ? substr ($root, 0, -1) : $root);
		
		# Ensure the image type is supported
		if (!eregi ('.(jpg|jpeg|gif|png)', $image)) {
			#!# Change to throwing 404
			echo "<p>\nThat image format is not supported.</p>";
			return false;
		}
		
		# Construct the filename
		$url = dirname ($_SERVER['REQUEST_URI']) . '/' . $image;
		$file = ($root ? $root : $_SERVER['DOCUMENT_ROOT']) . $url;
		
		# If the file does not exist, throw a 404
		if (!file_exists ($file)) {
			#!# Change to throwing 404
			echo "<p>\nThere is no such image.</p>";
			return false;
		}
		
		# Get the image size
		$file = str_replace ('%20', ' ', $file);
		list ($width, $height, $type, $imageSizeHtml) = getimagesize ($file);
		
		# Create the image HTML
		$html = "\n<img src=\"{$url}\" {$imageSizeHtml} alt=\"Image\" />";
		
		# Return the HTML
		return $html;
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
	function resize ($sourceFile, $outputFormat = 'jpg', $newWidth = '', $newHeight = '', $outputFile = false, $imageIsServerLocation = false, $watermark = false)
	{
		# Decode the $sourceFile to remove HTML entities
		$sourceFile = str_replace ('//', '/', ($imageIsServerLocation ? '' : $_SERVER['DOCUMENT_ROOT']) . str_replace ('%20', ' ', $sourceFile));
		if ($outputFile) {
			$outputFile = str_replace ('//', '/', $_SERVER['DOCUMENT_ROOT'] . str_replace ('%20', ' ', $outputFile));
		}
		
		# Check that the file exists for security reasons
		if (!file_exists ($sourceFile)) {echo '<p>Error: the selected file could not be found.</p>'; return false;}
		
		# Obtain the input format by taking the file extension, allowing for .jpeg and .jpg for JPG format
		$inputFileExtension = substr ($sourceFile, -4);
		if (substr ($sourceFile, -5) == '.jpeg') {$inputFileExtension = '.jpg';}
		
		# Obtain the source image
		switch (strtolower ($inputFileExtension)) {
				
			# GIF format
			case '.gif':
				$sourceFile = ImageCreateFromGIF ($sourceFile);
				break;
				
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
		
		# Add any watermark
		if ($watermark && is_callable ($watermark)) {
			#!# Needs to work for classes - is_callable is basically a mess
			$watermark (&$output, $newHeight);
		}
		
		# Send the image
		switch (strtolower ($outputFormat)) {
				
			# GIF format
			case 'gif':
				if (!$outputFile) {
					header ("Content-Type: image/gif");
					ImageGIF ($output);
				} else {
					ImageGIF ($output, $outputFile);
				}
				break;
				
			# JPG format
			case 'jpg':
			case 'jpeg':
				if (!$outputFile) {
					header ("Content-Type: image/jpg");
					ImageJPEG ($output);
				} else {
					ImageJPEG ($output, $outputFile);
				}
				break;
				
			# PNG format
			case 'png':
				if (!$outputFile) {
					header ("Content-Type: image/png");
					ImagePNG ($output);
				} else {
					ImagePNG ($output, $outputFile);
				}
				break;
				
			# If an invalid format has been requested, return false
			default:
				 echo '<p>Error: an unsupported output format was requested.</p>';
				 return false;
		}
		
		# Return true to signal success
		return true;
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
	
	
	/* Function not worth bothering with, because ImageCopyResampled doesn't have a workaround
	# Wrapper function for the imageCreate function (because PHP may not be compiled with GD2)
	function imageCreateWrapper ($width, $height)
	{
		# Determine the GD version
		#!# Check for function exists misses out GD2 compiled for PHP 4.1-4.3
		if (!function_exists ('gd_info')) {
			$gd2Supported = false;
		} else {
			$gdInfo = gd_info ();
			$gd2Supported = (strstr ($gdInfo['GD Version'], '2.') !== false);
		}
		
		# Version if GD2 support is present
		if ($gd2Supported) {
			return $output = ImageCreateTrueColor ($width, $height);
		}
		
		# Version if GD2 support is not present
		$output = imagecreate ($width, $height);
		$temporaryFile = './temporary.jpg';
		imageJPEG ($output, $temporaryFile);
		$output = imagecreatefromjpeg ($temporaryFile);
		unlink ($temporaryFile);
		return $output;
	}
	*/
	
	
	# Function to work out the dimensions of a scaled image
	function scale ($imageLocation, $size)
	{
		# Get the image's height and width
		list ($width, $height, $type, $imageSize) = getimagesize ($imageLocation);
		
		# Perform the scalings
		if ($width > $height) {
			$scaledWidth = $size;
			$scaledHeight = $height * ($scaledWidth / $width);
		} else {
			$scaledHeight = $size;
			$scaledWidth = $width * ($scaledHeight / $height);
		}
		
		# Return the width and height
		return array ($scaledWidth, $scaledHeight);
	}
	
	
	# Function to perform image renaming; WARNING: Only use if you know what you're doing!
	function renaming ($directory, $secondsOffset = 21600, $sortByDateNotName = false)
	{
		# Get the files
		$files = self::getImageList ($directory);
		if (!$files) {return false;}
		
		# Get the date for each file
		foreach ($files as $file => $attributes) {
			$sortedFiles[$file] = $attributes['time'];
		}
		
		# Sort by date/time if necessary
		if ($sortByDateNotName) {asort ($sortedFiles);}
		
		# Assign the date for each
		foreach ($sortedFiles as $file => $timestamp) {
			
			# Offset the time, so that e.g. for 21600, a new day 'starts' at 6am (21600 seconds past midnight)
			$timestamp -= $secondsOffset;
			
			# Assign the file date
			$fileDate = date ('ymd', $timestamp);
			
			# Start an entry for this date if not already present, or increment the character if not
			if (!isSet ($assignedNames[$fileDate])) {
				$assignedNames[$fileDate] = 'a';
			} else {
				$assignedNames[$fileDate]++;
			}
			
			# Convert the date tally to alphanumeric
			$base = 26;
			$set = floor ($assignedNames[$fileDate] / $base);
			$setPrefix = ($set ? chr (96 + $set) : '');
			
			# Construct the file extension
			$extension = '.' . strtolower ($files[$file]['extension']);
			
			# Construct the filename
			$renamedFiles[$file] = $fileDate . $setPrefix . $assignedNames[$fileDate] . $extension;
		}
		
		# Rename each file, or stop if there is a problem
		foreach ($renamedFiles as $old => $new) {
			if (!rename ($directory . $old, $directory . $new)) {return false;}
			echo "\nSuccessfully renamed: {$directory}<strong>{$old}</strong> &raquo; {$directory}<strong>{$new}</strong><br />";
		}
	}
	
	
	# Function to return the image dimensions of an image when scaled
	function scaledImageDimensions ($width, $height, $maximumDimension)
	{
		# Ensure the height and maximum dimension is legal or stop execution
		if (!is_numeric ($maximumDimension) || $maximumDimension == 0) {return false;}
		if (!is_numeric ($height) || $height == 0) {return false;}
		
		# Compute the new height and width, scaling down only if the original is greater than the base size
		$ratio = ($width / $height);
		if ($width > $height) {
			if ($width > $maximumDimension) {
				$width = $maximumDimension;
				$height = $width / $ratio;
			}
		} else {
			if ($height > $maximumDimension) {
				$height = $maximumDimension;
				$width = $height * $ratio;
			}
		}
		$width = round ($width);
		$height = round ($height);
		
		# Return the new width and height
		return array ($width, $height);
	}
}

?>