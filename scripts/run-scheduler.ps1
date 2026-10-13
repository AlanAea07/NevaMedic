$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
# Manual helper only. Scheduled tasks use wscript.exe with run-scheduler-hidden.vbs
# so the console is hidden before PHP and Laravel's child processes start.
$phpExecutable = 'C:\xampp\php\php.exe'
$artisanPath = Join-Path $projectDirectory 'artisan'
$schedulerProcess = Start-Process -FilePath $phpExecutable -ArgumentList @(('"' + $artisanPath + '"'), 'schedule:run') -WorkingDirectory $projectDirectory -WindowStyle Hidden -Wait -PassThru
exit $schedulerProcess.ExitCode
