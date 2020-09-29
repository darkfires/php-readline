<?php

/*   PHP Readline Class v0.8 - Copyright (C)2020 Len White <lwhite@nrw.ca>
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

class CLI {
	public $promptStr;
	public $cmdlist;
	public $ResponseTime;
	public $hostname;
	public $CurrentPath	= "";
	public $ExecOnStartup	= false;
	public $HistoryFile	= ".history";
	public $NoCommandFunc	= "NoCommandFunc";
	public $BrokenReadline	= false;
	public $TabCompletionList = array();

	public $color = array(
				"black"		=> "\033[01;30m",
				"red"		=> "\033[01;31m",
				"green"		=> "\033[01;32m",
				"yellow"	=> "\033[01;33m",
				"blue"		=> "\033[01;34m",
				"magenta"	=> "\033[01;35m",
				"cyan"		=> "\033[01;36m",
				"white"		=> "\033[01;37m",
				"clear"		=> "\033[00m",
			);

	public function __construct($cli="") {
//	      set_error_handler("CLI::exception_error_handler");

		if (function_exists("pcntl_signal")) {
			declare(ticks = 1);
			pcntl_signal(SIGTERM, "self::signal_handler");
			pcntl_signal(SIGHUP, "self::signal_handler");
			pcntl_signal(SIGINT, "self::signal_handler");
			pcntl_signal(SIGQUIT, "self::signal_handler");
			pcntl_signal(SIGABRT, "self::signal_handler");
			pcntl_signal(SIGUSR1, "self::signal_handler");
			pcntl_signal(SIGUSR2, "self::signal_handler");
		} else {
			echo "PHP pcntl module isn't loaded, interrupt catching won't work\n";
		}

		$this->LoadHistory();
		$this->ResponseTime = microtime(true);
	}

	public function InitTabCompletion() {
		if (empty($this->cmdlist)) {
			echo "WARNING: You are initializing tab completion without any commands to complete!\n";
			return true;
		}

		foreach ($this->cmdlist as $cmdidx => $command) {
			$this->TabCompletionList[] = $command["cmd"];
		}

		readline_completion_function(function($input, $index){
			$matches = array();

			if (!empty($this->TabCompletionList)) {
				foreach ($this->TabCompletionList as $command) {
					if (stripos($command, $input) !== false) {
						$matches[] = $command;
					}
				}
			}

			// Prevent Segfault
			if ($matches == false)
				$matches[] = '';

			return $matches;
		});

	}

	public function AddToCompletionList($files) {
		$this->TabCompletionList = array_merge($this->TabCompletionList, $files);
	}

	public function signal_handler($signal) {
		global $cfg;

		switch ($signal) {
			case SIGTERM:
			case SIGKILL:
				echo "\nCaught ".($signal == 15 ? "SIGTERM" : "SIGKILL").". Cleaning up and exiting...\n";
				self::callexit();
			case SIGINT:
				if (isset($cfg["busyLoop"]) && $cfg["busyLoop"]) {
					echo "\nCtrl-C pressed, caught SIGINT.  Breaking...\n";
					$cfg["busyLoop"] = 0;
					return true;
				} else {
					echo "\nCtrl-C pressed, caught SIGINT.  Cleaning up and exiting...\n";
					self::callexit();
				}
			default:
				echo "\nUnhandled signal $signal received...\n";
		}
	}

	public function GetPromptStr($ms=false) {
		if (strpos($this->promptStr, '%h') !== false && !empty($this->hostname)) {
			$prompt = str_replace('%h', $this->hostname, $this->promptStr);
		} else
			$prompt = $this->promptStr;

		if (strpos($prompt, '%t') !== false) {
			$prompt = str_replace('%t', date("m.d.y H:i:s"), $prompt);
		}

		if (strpos($prompt, '%c') !== false) {
			$prompt = str_replace('%c', $this->CurrentPath, $prompt);
		}

		if (strpos($prompt, '%T') !== false) {
			$ms = (microtime(true) - $this->ResponseTime);
			$prompt = str_replace('%T', number_format($ms,2)."ms", $prompt);
		}

		if (strpos($prompt, '%BLUE') !== false) {
			$prompt = str_replace('%BLUE', $this->color["blue"], $prompt);
		}

		if (strpos($prompt, '%RED') !== false) {
			$prompt = str_replace('%RED', $this->color["red"], $prompt);
		}

		if (strpos($prompt, '%GREEN') !== false) {
			$prompt = str_replace('%GREEN', $this->color["green"], $prompt);
		}

		if (strpos($prompt, '%BLACK') !== false) {
			$prompt = str_replace('%BLACK', $this->color["black"], $prompt);
		}

		if (strpos($prompt, '%MAGENTA') !== false) {
			$prompt = str_replace('%MAGENTA', $this->color["magenta"], $prompt);
		}

		if (strpos($prompt, '%WHITE') !== false) {
			$prompt = str_replace('%WHITE', $this->color["white"], $prompt);
		}

		if (strpos($prompt, '%CYAN') !== false) {
			$prompt = str_replace('%CYAN', $this->color["cyan"], $prompt);
		}

		if (strpos($prompt, '%YELLOW') !== false) {
			$prompt = str_replace('%YELLOW', $this->color["yellow"], $prompt);
		}

		if (strpos($prompt, '%CLEAR') !== false) {
			$prompt = str_replace('%CLEAR', $this->color["clear"], $prompt);
		}

		return $prompt;
	}

	public function SetPromptStr($str) {
		$this->promptStr = $str;
	}

	public function SetCommands($cmds) {
		$this->cmdlist = $cmds;
	}

	public function Alias($sargs, $sargc) {
		if ($sargc == 0) {
			array_walk($this->cmdlist, function(&$v, $k) {
				if (isset($v["alias"]))
					echo sprintf("alias %s='%s'\n", $v["alias"], $v["alias_cmd"]);
			});
		} else {
			$args = implode(" ", $sargs);

			$equalPos = strpos($args, '=');

			if ($equalPos !== false) {
				$alias = substr($args, 0, $equalPos);
				$alias_cmd = substr($args, $equalPos+1);

				echo "new alias: {$alias}='{$alias_cmd}'\n";

				$this->cmdlist[] = array("alias" => $alias, "alias_cmd" => $alias_cmd);
			}
		}
	}

	public function LoadHistory() {
		if ($this->ExecOnStartup !== false)
			return true;

		if (file_exists($this->HistoryFile)) {
			echo "Loading saved history from {$this->HistoryFile}: ";
			readline_read_history($this->HistoryFile);
			echo "done\n";
		}
	}

	public function SaveHistory() {
		if ($this->ExecOnStartup !== false)
			return true;

		readline_write_history($this->HistoryFile);
	}

	public function ShowHistory() {
		$HistoryList = readline_list_history();

		foreach ($HistoryList as $histidx => $history) {
			echo "{$histidx}: {$history}\n";
		}

		echo __FUNCTION__.": ".count($hist)." items\n";
	}

	public function rl_callback($rline, $alias=false) {
		global $c, $prompting;

		$info = readline_info();

		$this->ResponseTime = microtime(true);

		$siPos  = strpos($rline, ' ');
		$sargc  = 0;
		$sargs  = array();

		if ($siPos !== false) {
			$scmd = substr($rline, 0, $siPos);
			$sargs= explode(" ", substr($rline, $siPos+1));
			$sargc= count($sargs);
		} else {
			$sargs= array();
			$scmd = $rline;
		}

		if (!$alias)
			readline_add_history($rline);

		$findFunction = false;

		foreach ($this->cmdlist as $v) {
			if (isset($v["alias"]) && $v["alias"] == $scmd && !$alias) {
				$newcmd = $v["alias_cmd"] . " " . implode(" ", $sargs);
				echo "Alias: {$v["alias"]} -> new cmd {$newcmd}\n";

				$this->rl_callback($newcmd, true);

				return true;
			}
			else if (isset($v["func"]) && $v["cmd"] == $scmd)
			{
				if (method_exists($this, $v["func"])) {
					$retval = $this->{$v["func"]}($sargs, $sargc);
					$findFunction = true;
				} else if (function_exists($v["func"])) {
					$retval = $v["func"]($sargs, $sargc);
					$findFunction = true;
				} else {
					echo "Function {$v["func"]} doesn't exist\n";
				}
			}
			else if (isset($v["sfunc"]) && $v["cmd"] == $scmd)
			{
				echo basename(__FILE__).":".__LINE__.": call S{$v["sfunc"]}\n";

				if (method_exists($this, $v["sfunc"])) {
					$retval = $this->{$v["sfunc"]}();
					$findFunction = true;
				} else if (function_exists($v["sfunc"])) {
					$retval = $v["sfunc"]();
					$findFunction = true;
				} else {
					echo "Function (noargs) {$v["sfunc"]} doesn't exist\n";
				}

				$findFunction = true;
			}
		}

		if (!$findFunction) {
			$args = explode(" ", $rline);

			if (method_exists($this, $this->NoCommandFunc)) {
				echo __FILE__.":".__LINE__.": Call \$this->{$this->NoCommandFunc})($sargs, $sargc)\n";
				$this->{$this->NoCommandFunc}($args, $sargc);
				$findFunction = true;
			} else if (function_exists($this->NoCommandFunc)) {
				echo __FILE__.":".__LINE__.": Call call_user_func({$this->NoCommandFunc}), $sargs, $sargc)\n";
				call_user_func($this->NoCommandFunc, $args, $sargc);
				$findFunction = true;
			}

			if (!$findFunction)
				echo "No function found {$this->NoCommandFunc}\n";
		}

		if ($this->BrokenReadline) {
			echo $this->GetPromptStr();
			readline_callback_handler_install('', 'self::rl_callback');
		} else
			readline_callback_handler_install($this->GetPromptStr(), 'self::rl_callback');
	}

	public function rl_info() {
		global $cfg, $c;
		$info = readline_info();

		$lbuf = $info["line_buffer"];
		$lchr = substr($lbuf, strlen($lbuf)-1);
		$readrefresh = 0;

		if ($lbuf == "?") {
			echo "\n";
			$this->printHelp();
//echo print_r($info,1);
			readline_callback_handler_remove();

			if ($this->BrokenReadline) {
				echo $this->GetPromptStr();
				readline_callback_handler_install('', 'self::rl_callback');
			} else
				readline_callback_handler_install($this->GetPromptStr(), 'self::rl_callback');

//			readline_info("line_buffer", '');
//			readline_info("erase_empty_line", 1);
//			readline_on_new_line();
//			readline_redisplay();
		}
	}

	public function noCommandFunc($args) {
		echo "No such command: {$args}\n";
	}

	public function printHelp($cmdmatch = "", $argc=0) {
		$cmdlen = strlen($cmdmatch);
		$matched= 0;

		array_walk($this->cmdlist, function(&$v, $k) use (&$cmdmatch, &$cmdlen, &$matched) {
			if (!$cmdlen || ($cmdlen && !strncmp($cmdmatch, $v["cmd"], $cmdlen))) {
//				if (!$matched)
//					echo "\n";

				if (isset($v["cmd"]))
					echo sprintf("%-20s - %s\n", $v["cmd"] . (isset($v["args"]) ? " {$v["args"]}" : ""), $v["desc"]);
				else
					echo sprintf("Alias: %-20s - %s\n", $v["alias"], $v["alias_cmd"]);

				$matched = 1;
			}
		});

		echo "\n";

		return ($cmdlen ? ($matched ? true : false) : false);
	}

	public function callexit() {
		$this->SaveHistory();
		readline_callback_handler_remove();
		exit;
	}

	public function CLILoop() {
		$prompting = true;

		if ($this->BrokenReadline) {
			echo $this->GetPromptStr();
			readline_callback_handler_install('', 'self::rl_callback');
		} else
			readline_callback_handler_install($this->GetPromptStr(), 'self::rl_callback');

//		$this->InitCompletion();

		while ($prompting) {
			$w = NULL;
			$e = NULL;
			$n = @stream_select($r = array(STDIN), $w, $e, null);

			if ($n && in_array(STDIN, $r)) {
				// read a character, will call the callback when a newline is entered
				readline_callback_read_char();
				$this->rl_info();
			}
		}
	}
}

if (!isset($autodetect_ver)) {

$cli = new CLI();
$cli->hostname = "192.168.209.1";
$cli->SetPromptStr("%GREEN%h %MAGENTA%t %c %T%CLEAR# ");
$cli->SetCommands(array(array("cmd" => "lset",		"args" => "<var> <value>",	"desc" => "Change runtime variables"),
			array("cmd" => "save",		"args" => "<src> <dst>",	"desc" => "Download remote file"),
			array("cmd" => "eiperf",	"args" => "<cmd>",		"desc" => "iperf exploit method"),
			array("cmd" => "dev",		"args" => "[-f]",		"desc" => "Device Information",
					"func" => "DeviceInfo"),
			array("cmd" => "devsave",	"args" => "[-f]", 		"desc" => "Download Remote Device CFG",
					"func" => "DeviceCfgSave"),
			array("cmd" => "findtower",	"desc" => "Find nearest tower"),
			array("cmd" => "netinf",	"desc" => "Network Interface Information",	"func" => "ShowNetInfo"),
			array("cmd" => "stop",		"desc" => "Disconnect from LTE/WiMAX provider"),
			array("cmd" => "start",		"desc" => "Connect to LTE/WiMAX provider"),
			array("cmd" => "status",	"desc" => "System and Signal Status", 	  	"func" => "Status"),
			array("cmd" => "reset",		"desc" => "Reset stuck/hung remote command"),
			array("cmd" => "hide",		"desc" => "Hide remote commands saved in WebGUI"),
			array("cmd" => "history",	"desc" => "Command history",	"func" => "ShowHistory"),
			array("cmd" => "exit",		"desc" => "Disconnect and exit",		"func" => "callexit"),
			array("cmd" => "help",		"desc" => "This help screen",			"sfunc" => "printHelp"),
			array("alias" => "ls",		"alias_cmd" => "ls --color -F"),
			));

$cli->CLILoop();

}

?>
