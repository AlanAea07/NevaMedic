Option Explicit

Dim shell, fileSystem, projectDirectory, phpExecutable, artisanPath, command, exitCode
Set shell = CreateObject("WScript.Shell")
Set fileSystem = CreateObject("Scripting.FileSystemObject")

projectDirectory = fileSystem.GetParentFolderName(fileSystem.GetParentFolderName(WScript.ScriptFullName))
phpExecutable = "C:\xampp\php\php.exe"
artisanPath = fileSystem.BuildPath(projectDirectory, "artisan")

If Not fileSystem.FileExists(phpExecutable) Then WScript.Quit 2
If Not fileSystem.FileExists(artisanPath) Then WScript.Quit 2

shell.CurrentDirectory = projectDirectory
command = """" & phpExecutable & """ """ & artisanPath & """ schedule:run --no-interaction --no-ansi"

' Start PHP with its console hidden from creation. Laravel's child processes
' inherit that console. Wait and preserve the exit code for Task Scheduler.
exitCode = shell.Run(command, 0, True)
WScript.Quit exitCode
