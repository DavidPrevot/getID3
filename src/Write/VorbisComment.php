<?php

namespace JamesHeinrich\GetID3\Write;

use JamesHeinrich\GetID3\Utils;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// write.vorbiscomment.php                                     //
// module for writing VorbisComment tags                       //
//                                                            ///
/////////////////////////////////////////////////////////////////

class VorbisComment
{
	/**
	 * @var string
	 */
	public $filename;

	/**
	 * @var array
	 */
	public $tag_data;

	/**
	 * Any non-critical errors will be stored here.
	 *
	 * @var array
	 */
	public $warnings = array();

	/**
	 * Any critical errors will be stored here.
	 *
	 * @var array
	 */
	public $errors   = array();

	/**
	 * @return bool
	 */
	public function WriteVorbisComment() {

		// Create file with new comments
		$tempcommentsfilename = tempnam(Utils::getTempDirectory(), 'getID3');
		if (Utils::isWritable($tempcommentsfilename) && is_file($tempcommentsfilename) && ($fpcomments = fopen($tempcommentsfilename, 'wb'))) {

			foreach ($this->tag_data as $key => $value) {
				foreach ($value as $commentdata) {
					fwrite($fpcomments, $this->CleanVorbisCommentName($key).'='.$commentdata."\n");
				}
			}
			fclose($fpcomments);

		} else {
			$this->errors[] = 'failed to open temporary tags file "'.$tempcommentsfilename.'", tags not written';
			return false;
		}

		$oldignoreuserabort = ignore_user_abort(true);
		if (Utils::isWindows()) {

			if (file_exists(Utils::getHelperAppDirectory() . 'vorbiscomment.exe')) {
				//$commandline = '"'.Utils::getHelperAppDirectory().'vorbiscomment.exe" -w --raw -c "'.$tempcommentsfilename.'" "'.str_replace('/', '\\', $this->filename).'"';
				//  vorbiscomment works fine if you copy-paste the above commandline into a command prompt,
				//  but refuses to work with `backtick` if there are "doublequotes" present around BOTH
				//  the metaflac pathname and the target filename. For whatever reason...??
				//  The solution is simply ensure that the metaflac pathname has no spaces,
				//  and therefore does not need to be quoted

				// On top of that, if error messages are not always captured properly under Windows
				// To at least see if there was a problem, compare file modification timestamps before and after writing
				clearstatcache();
				$timestampbeforewriting = filemtime($this->filename);

				$commandline = Utils::getHelperAppDirectory() . 'vorbiscomment.exe -w --raw -c "' . $tempcommentsfilename . '" "' . $this->filename . '" 2>&1';
				$VorbiscommentError = `$commandline`;

				if (empty($VorbiscommentError)) {
					clearstatcache();
					if ($timestampbeforewriting == filemtime($this->filename)) {
						$VorbiscommentError = 'File modification timestamp has not changed - it looks like the tags were not written';
					}
				}
			} else {
				$VorbiscommentError = 'vorbiscomment.exe not found in ' . Utils::getHelperAppDirectory();
			}

		} else {

			$commandline = 'vorbiscomment -w --raw -c "'.$tempcommentsfilename.'" "'.$this->filename.'" 2>&1';
			$VorbiscommentError = `$commandline`;

		}

		// Remove temporary comments file
		unlink($tempcommentsfilename);
		ignore_user_abort($oldignoreuserabort);

		if (!empty($VorbiscommentError)) {

			$this->errors[] = 'system call to vorbiscomment failed with message: '."\n\n".$VorbiscommentError;
			return false;

		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function DeleteVorbisComment() {
		$this->tag_data = array(array());
		return $this->WriteVorbisComment();
	}

	/**
	 * @param string $originalcommentname
	 *
	 * @return string
	 */
	public function CleanVorbisCommentName($originalcommentname) {
		// A case-insensitive field name that may consist of ASCII 0x20 through 0x7D, 0x3D ('=') excluded.
		// ASCII 0x41 through 0x5A inclusive (A-Z) is to be considered equivalent to ASCII 0x61 through
		// 0x7A inclusive (a-z).

		// replace invalid chars with a space, return uppercase text
		// Thanks Chris Bolt <chris-getid3Øbolt*cx> for improving this function
		// note: *reg_replace() replaces nulls with empty string (not space)
		return strtoupper(preg_replace('#[^ -<>-}]#', ' ', str_replace("\x00", ' ', $originalcommentname)));

	}

}
