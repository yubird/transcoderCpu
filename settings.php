<?php
// デバッグモード
define('DEBUG', false);
// chinachuのrecorded.jsonを指定
define('RECORDED_FILE', '/usr/local/chinachu/data/recorded.json');
// chinachu API経由のrecorded
define('RECORDED_API', 'http://system:152240a@192.168.206.3:20772/api/recorded.json');
// ffmpeg
//define('FFMPEG_BINARY', '/usr/local/chinachu/usr/bin/ffmpeg');
define('FFMPEG_BINARY', '/usr/local/bin/ffmpeg');
// Tssplitter
//define('TSSPLITTER_BINARY', 'wine /root/.wine/drive_c/Program\ Files/TsSplitter.exe -EIT -ECM -1SEG -SEP -OVL10 -OUT');
define('TSSPLITTER_BINARY', 'wine /root/.wine/drive_c/Program\ Files/TsSplitter.exe -EIT -ECM -1SEG -OUT');
// Caption2ASS
define('CAPTION2ASS_BINARY', 'timeout 30 wine /root/.wine/drive_c/Caption2Ass/Caption2AssC.exe -format srt');
// tmp directory
define('WORKING_DIR', '/mnt/pool/transcode/');
// 1回の実行中に何ファイル変換するか
define('MAX_PROCESS', 5);
// 全力出しちゃう？
define('MAX_THREADS', 12);
// 変換処理1回毎の休憩時間(秒)
define('SLEEP_TIME', 20);
// 変換後ファイルチェックで正常とみなすサイズ(bytes)
define('SUCCESS_FILESIZE', 524288);
// 変換後に元ファイルを削除するか
define('DELETE_SOURCE_FILE', true);
// VTTfile path
define('VTT_PATH', '/var/www/video/vtt/');
?>
