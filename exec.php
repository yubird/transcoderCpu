<?php
include dirname(__FILE__).'/settings.php';

/**
 * chinachu autoTranscode script
 */
function isSd($input) {
	$command = FFMPEG_BINARY.' -y -i "'.$input.'" 2>&1';
	exec($command, $output);
	$regexp = '/Video: mpeg2video.* 720x480 /';
	foreach ($output as $line) {
		if (preg_match($regexp, $line)) {
			return true;
		}
	}
	return false;
}

function is1440($input) {
	$command = FFMPEG_BINARY.' -y -i "'.$input.'" 2>&1';
	exec($command, $output);
	$regexp = '/Video: mpeg2video.* 1440x1080 /';
	foreach ($output as $line) {
		if (preg_match($regexp, $line)) {
			return true;
		}
	}
	return false;
}

function exportCaption($input, $output) {
	echo "Export caption\n";
	$command = CAPTION2ASS_BINARY.' "'.$input.'" "'.$output.'"';
	exec($command, $out, $ret);
	if ($ret == 0) {
		$srt = $output.'.srt';
		$vtt = $output.'.vtt';
		$command = FFMPEG_BINARY.' -i '.$srt.' '.$vtt.' 2>/dev/null';
		exec($command);
		if (@filesize($vtt) > 1024) {
			return true;
		}
	}
	if (file_exists($output)) {
		unlink($output);
	}
	return false;
}

try {
	echo "Starting transcode...\n";
	if (isset($argv[1]) && $argv[1] != "") {
		echo "Direct input mode.\n";
		if (!file_exists($argv[1])) {
			echo "Error: file not found.\n";
			exit(1);
		}
		if (isset($argv[2]) && is_numeric($argv[2]) && $argv[2] == '1') {
			$fullHd = true;
		} else {
			$fullHd = false;
		}
		$input = $argv[1];
		$tmpFilename = splitTs($input);
		$output = newFilename($input);
		echo "new filename is ".$output."\n";
		$ret = trans($tmpFilename, $output, $fullHd, isSd($tmpFilename));
		deleteTmpfile($input);
		if ($ret == 0) {
			// 正常終了
			if (filesize($output) > SUCCESS_FILESIZE) {
				if (!DEBUG && DELETE_SOURCE_FILE) {
					unlink($input);
				}
				exit(0);
			}
		}
		exit(1);
	}
	$programs = loadRecorded();
	$count = 0;

	foreach ($programs as &$program) {
		if (!property_exists($program, 'isTranscoded')) {
			$program->isTranscoded = false;
		}
		if ($program->isTranscoded) {
			// 変換済スルー
			continue;
		}
		if (!file_exists($program->recorded)) {
			// ファイル存在しない
			continue;
		}
		// 未変換program
		$transcoded = newFilename($program->recorded);
		echo "transcode filename: $transcoded\n";
		if (file_exists($transcoded)) {
			// ファイル存在
			echo "WARNING: overwrite file.\n";
		}
		$tmpFilename = splitTs($program->recorded);
		if ($program->category == 'anime') {
			$ret = trans($tmpFilename, $transcoded, true, false);
		} else {
			$ret = trans($tmpFilename, $transcoded, false, isSd($tmpFilename));
		}
		deleteTmpfile($program->recorded);
		if ($ret == 0) {
			// 正常終了
			if (filesize($transcoded) > SUCCESS_FILESIZE) {
				$count += 1;
			} else {
				echo "Error: too small transcoded file.\n";
				if (file_exists($transcoded)) {
					unlink($transcoded);
				}
				continue;
			}
			// JSONのisTranscodedを立てる
			// JSON読み込みからここまでの間に更新されてるかも?
			$oldFile = $program->recorded;
			$relProgs = loadRecorded();
			foreach ($relProgs as &$relProg) {
				if ($program->id === $relProg->id) {
					// IDマッチで更新
					$relProg->isTranscoded = true;
					$relProg->recorded = $transcoded;
					$newJson = json_encode(
						$relProgs,
						JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
					);
					$write = file_put_contents(
						RECORDED_FILE, $newJson, LOCK_EX
					);
					touch(RECORDED_FILE);
					// API経由でrecordedを取得してchinachuに読み込ませる
					loadRecorded(true);
					if ($write === false) {
						echo "Error: can't write recorded file.\n";
					} else {
						if (!DEBUG && DELETE_SOURCE_FILE) {
							unlink($oldFile);
						}
					}
					break;
				}
			}
		} else {
			// 異常終了
			echo "Error: can't transcode file.\n";
			if (file_exists($transcoded)) {
				unlink($transcoded);
			}
			continue;
		}
		// 最大実行回数に達したら終了
		if ($count >= MAX_PROCESS) {
			break;
		}
		// 休憩
		sleep(SLEEP_TIME);
	}
	exit(0);
} catch (Exception $e) {
	echo "Error: ".$e->getMessage()."\n";
	exit(1);
}

function trans($from, $to, $fullHd, $sd) {
	$to = str_replace('//', '/', $to);
	$uuid = uniqid();
	$tmpTo = dirname($from).'/'.$uuid.'.mp4';
	$capFile = dirname($from).'/'.$uuid;
	$caption = exportCaption($from, $capFile);
	if ($caption) {
		$md5 = md5($to);
		$split = array();
		for ($i = 0; $i < 4; ++$i) {
			$split[] = substr($md5, ($i * 2), 2);
		}
		$vttPath = VTT_PATH.implode($split, '/').'/'.$md5.'.vtt';
		if (!file_exists(dirname($vttPath))) {
			mkdir(dirname($vttPath), 0755, true);
		}
		if (file_exists($vttPath)) {
			unlink($vttPath);
		}
		rename($capFile.'.vtt', $vttPath);
		chmod($vttPath, 0644);
		echo "Caption file exported: $vttPath\n";
	}
	$return = 0;
	$command = FFMPEG_BINARY.' -y '
		.'-i "'.$from.'" ';
	if (DEBUG) {
		$command .= '-ss 0 -t 120 ';
	}
	if ($fullHd && is1440($from)) {
		$is1440 = true;
	} else {
		$is1440 = false;
	}

	if ($fullHd) {
		$crf = 24;
		if ($is1440) {
			$res = '1440x1080';
		} else {
			$res = '1920x1080';
		}
		$maxrate = 10240;
		$bufsize = 16384;
		$qpmin = 16;
		$bf = 5;
		$ref = 4;
		$sc = 60;
		$nr = 140;
		$aq = '0.84';
		$cqm = 'flat';
		$merange = 16;
		$deblock = '0,1';
		$vf = 'bwdif=1:-1:1,hqdn3d';
		$qcomp = '0.78';
	} else {
		$bufsize = 10234;
		$deblock = '1,1';
		$bf = 5;
		$ref = 3;
		$merange = 16;
		$cqm = 'jvt';
		$sc = 45;
		$qcomp = '0.66';
		$qpmin = 17;
		$vf = 'yadif=0:-1:1,hqdn3d';
		$nr = 160;
		if ($sd) {
			$crf = 24;
			$res = '720x480';
			$maxrate = 6144;
			$aq = '0.74';
		} else {
			$crf = 25;
			$res = '1280x720';
			$maxrate = 8633;
			$aq = '0.74';
		}
	}
	$command .= '-f mp4 '
		.'-threads '.MAX_THREADS.' '
		.'-profile:v high '
		.'-vsync 1 '
		.'-vcodec libx264 '
		.'-cmp chroma '
		.'-tune zerolatency '
		.'-partitions +parti4x4+parti8x8+partp8x8+partb8x8 '
		.'-flags +loop '
		.'-vf "'.$vf.'" '
		.'-s '.$res.' '
		.'-aspect 16:9 '
		.'-brand mp42 '
		.'-pix_fmt yuv420p '
		.'-x264opts '
		.'qcomp='.$qcomp.':bframes='.$bf.':'
		.'cqm='.$cqm.':threads='.MAX_THREADS.':'
		.'nr='.$nr.':aq-mode=2:aq-strength='.$aq.':psy=0:'
		.'deblock='.$deblock.':ref='.$ref.':scenecut='.$sc.':'
		.'crf='.$crf.':b-adapt=2:b-pyramid:8x8dct:mixed-refs:'
		.'rc-lookahead=120:direct=auto:weightp=2:'
		.'chroma-qp-offset=1:weightb=1:'
		.'trellis=1:qpmin='.$qpmin.':qpstep=16:keyint=120:min-keyint=30:'
		.'mbtree=1:me=umh:subme=8:merange='.$merange.':'
		.'vbv-maxrate='.$maxrate.':vbv-bufsize='.$bufsize.':'
		.'colormatrix=bt709:colorprim=bt709:transfer=bt709 '
		.'-acodec libfdk_aac -ac 2 -ar 48000 -ab 144k '
		.'-ignore_unknown '
		.'-movflags '
		.'faststart '
		.'"'.$tmpTo.'"';
	passthru($command, $return);
	if ($return == 0) {
		rename($tmpTo, $to);
	} else {
		if (file_exists($tmpTo)) {
			unlink($tmpTo);
		}
	}
	return $return;
}

function newFilename($oldFilename) {
	return str_ireplace(Array('.m2ts', '.ts'), '.mp4', $oldFilename);
}

function loadRecorded($fromHttp = false) {
	if ($fromHttp) {
		$json = file_get_contents(RECORDED_API);
	} else {
		$json = file_get_contents(RECORDED_FILE);
	}
	return json_decode($json);
}

function splitTs($file) {
	echo "Split TS file\n";
	$md5 = md5($file);
	if (!file_exists(WORKING_DIR.$md5)) {
		mkdir(WORKING_DIR.$md5);
	}
	$command = TSSPLITTER_BINARY.' "'.WORKING_DIR.$md5.'" "'.$file.'" 2>/dev/null';
	exec($command);
	$command = 'ls -S '.WORKING_DIR.$md5.'|grep -e ".ts" -e ".m2ts"|head -n 1';
	exec($command, $output);
	if (isset($output[0]) && file_exists(WORKING_DIR.$md5.'/'.$output[0])) {
		return WORKING_DIR.$md5.'/'.$output[0];
	}
	return null;
}

function deleteTmpfile($file) {
	$md5 = md5($file);
	$path = WORKING_DIR.$md5;
	$command = 'rm -Rf "'.$path.'"';
	exec($command);
}
?>
