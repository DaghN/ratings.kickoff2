# Convert one audio file to 44.1 kHz stereo WAV or 320 kbps MP3 with the converter built into Windows (no ffmpeg needed).
# Used for jukebox tracks: docs/k2-jukebox-popup.md, "Adding a track".
# All paths must be absolute. Example:
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\oneoff\jukebox_transcode.ps1 -In C:\tmp\clip.m4a -OutDir C:\tmp -OutName clip.mp3 -Kind mp3
# Reads m4a and WAV. It refused the existing jukebox MP3s ("cannot transcode: Unknown"), so it is not an MP3 decoder.
param([string]$In,[string]$OutDir,[string]$OutName,[string]$Kind='wav',[double]$TrimStart=0,[double]$TrimEnd=0)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
[Windows.Storage.StorageFile,Windows.Storage,ContentType=WindowsRuntime] | Out-Null
[Windows.Storage.StorageFolder,Windows.Storage,ContentType=WindowsRuntime] | Out-Null
[Windows.Media.Transcoding.MediaTranscoder,Windows.Media.Transcoding,ContentType=WindowsRuntime] | Out-Null
[Windows.Media.Transcoding.PrepareTranscodeResult,Windows.Media.Transcoding,ContentType=WindowsRuntime] | Out-Null
[Windows.Media.MediaProperties.MediaEncodingProfile,Windows.Media.MediaProperties,ContentType=WindowsRuntime] | Out-Null
$m=[System.WindowsRuntimeSystemExtensions].GetMethods()
$asTask=($m | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' })[0]
$asAct=($m | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncActionWithProgress`1' })[0]
function Wait-Op($op,$type){ $t=$asTask.MakeGenericMethod($type).Invoke($null,@($op)); $t.Wait(); $t.Result }
$src=Wait-Op ([Windows.Storage.StorageFile]::GetFileFromPathAsync($In)) ([Windows.Storage.StorageFile])
$fold=Wait-Op ([Windows.Storage.StorageFolder]::GetFolderFromPathAsync($OutDir)) ([Windows.Storage.StorageFolder])
$dst=Wait-Op ($fold.CreateFileAsync($OutName,[Windows.Storage.CreationCollisionOption]::ReplaceExisting)) ([Windows.Storage.StorageFile])
if($Kind -eq 'mp3'){ $p=[Windows.Media.MediaProperties.MediaEncodingProfile]::CreateMp3([Windows.Media.MediaProperties.AudioEncodingQuality]::High); $p.Audio.Bitrate=320000 }
else { $p=[Windows.Media.MediaProperties.MediaEncodingProfile]::CreateWav([Windows.Media.MediaProperties.AudioEncodingQuality]::High) }
$p.Audio.SampleRate=44100; $p.Audio.ChannelCount=2
$tr=New-Object Windows.Media.Transcoding.MediaTranscoder
$tr.AlwaysReencode=$true
if($TrimStart -gt 0){ $tr.TrimStartTime=[TimeSpan]::FromSeconds($TrimStart) }
if($TrimEnd -gt 0){ $tr.TrimStopTime=[TimeSpan]::FromSeconds($TrimEnd) }
$prep=Wait-Op ($tr.PrepareFileTranscodeAsync($src,$dst,$p)) ([Windows.Media.Transcoding.PrepareTranscodeResult])
if(-not $prep.CanTranscode){ throw "cannot transcode: $($prep.FailureReason)" }
$t=$asAct.MakeGenericMethod([double]).Invoke($null,@($prep.TranscodeAsync())); $t.Wait()
"done $OutName"
