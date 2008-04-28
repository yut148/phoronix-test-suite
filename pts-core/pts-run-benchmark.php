<?php

/*
   Copyright (C) 2008, Michael Larabel.
   Copyright (C) 2008, Phoronix Media.

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

require("pts-core/functions/pts-functions.php");
require("pts-core/functions/pts-functions-run.php");
require("pts-core/functions/pts-functions-merge.php");

$TO_RUN = strtolower($argv[1]);
$TO_RUN_TYPE = pts_benchmark_type($TO_RUN);
$BENCHMARK_RAN = false;

if(isset($argv[2]) && $argv[2] == "BATCH")
	define("PTS_BATCH_MODE", "1");

if(empty($TO_RUN))
{
	echo "\nThe benchmark, suite name, or saved file name must be supplied.\n";
	exit;
}
// Make sure tests are installed
pts_verify_test_installation($TO_RUN);

pts_monitor_update(); // Update sensors, etc

// Kill the screensaver
$screensaver_status = trim(shell_exec("gconftool -g /apps/gnome-screensaver/idle_activation_enabled 2>&1"));

if($screensaver_status == "true")
{
	shell_exec("gconftool --type bool --set /apps/gnome-screensaver/idle_activation_enabled false 2>&1");
	define("SCREENSAVER_KILLED", 1);
}

if(!$TO_RUN_TYPE)
{
	if(is_file(pts_input_correct_results_path($TO_RUN)))
	{
		$SAVE_RESULTS = true;
		$TO_RUN_TYPE = "LOCAL_COMPARISON";
		$PROPOSED_FILE_NAME = $TO_RUN;
		$RES_NULL = null;

		if(!defined("PTS_BATCH_MODE") || pts_read_user_config("PhoronixTestSuite/Options/BatchMode/PromptForTestIdentifier", "TRUE") == "TRUE")
			do
			{
				echo "Enter a unique identifier for distinguishing this series of tests: ";
				$RESULTS_IDENTIFIER = trim(str_replace(array('/'), '', fgets(STDIN)));
			}while(empty($RESULTS_IDENTIFIER));

		$RESULTS = new tandem_XmlWriter();
	}
	else if(trim(@file_get_contents("http://www.phoronix-test-suite.com/global/profile-check.php?id=$TO_RUN")) == "REMOTE_FILE")
	{
		$SAVE_RESULTS = true;
		$TO_RUN_TYPE = "GLOBAL_COMPARISON";
		$PROPOSED_FILE_NAME = $TO_RUN;
		$RES_NULL = null;
		define("GLOBAL_COMPARISON", 1);

		pts_save_result($PROPOSED_FILE_NAME . "/composite.xml", @file_get_contents("http://www.phoronix-test-suite.com/global/pts-results-viewer.php?id=$TO_RUN"));

		if(!defined("PTS_BATCH_MODE") || pts_read_user_config("PhoronixTestSuite/Options/BatchMode/PromptForTestIdentifier", "TRUE") == "TRUE")
			do
			{
				echo "Enter a unique identifier for distinguishing this series of tests: ";
				$RESULTS_IDENTIFIER = trim(str_replace(array('/'), '', fgets(STDIN)));
			}while(empty($RESULTS_IDENTIFIER));

		$RESULTS = new tandem_XmlWriter();
	}
	else
	{
		"\n$TO_RUN is not a recognized benchmark, suite, or PTS Global ID. Exiting...\n";
		exit(0);
	}
}
else
{
	$SAVE_RESULTS = pts_bool_question("Would you like to save these benchmark results (Y/n)?", true, "SAVE_RESULTS");

	if($SAVE_RESULTS)
	{
		do
		{
			echo "Enter a name to save these results: ";
			$PROPOSED_FILE_NAME = trim(fgets(STDIN));
		}while(empty($PROPOSED_FILE_NAME));

		$CUSTOM_TITLE = $PROPOSED_FILE_NAME;
		$PROPOSED_FILE_NAME = trim(str_replace(array(' ', '/', '&', '\''), "", strtolower($PROPOSED_FILE_NAME))); // Clean up name

		if(empty($PROPOSED_FILE_NAME))
			$PROPOSED_FILE_NAME = date("Y-m-d-Hi");

		if(!defined("PTS_BATCH_MODE") || pts_read_user_config("PhoronixTestSuite/Options/BatchMode/PromptForTestIdentifier", "TRUE") == "TRUE")
			do
			{
				echo "Enter a unique identifier for distinguishing this series of tests: ";
				$RESULTS_IDENTIFIER = trim(str_replace(array('/'), '', fgets(STDIN)));
			}while(empty($RESULTS_IDENTIFIER));

		$RESULTS = new tandem_XmlWriter();
	}
}

if(!isset($RESULTS_IDENTIFIER) || empty($RESULTS_IDENTIFIER))
	$RESULTS_IDENTIFIER = date("Y-m-d H:i");

if($TO_RUN_TYPE == "BENCHMARK")
{
	$xml_parser = new tandem_XmlReader(file_get_contents(XML_PROFILE_DIR . "$TO_RUN.xml"));
	$settings_name = $xml_parser->getXMLArrayValues("PTSBenchmark/Settings/Option/DisplayName");
	$settings_argument = $xml_parser->getXMLArrayValues("PTSBenchmark/Settings/Option/ArgumentName");
	$settings_identifier = $xml_parser->getXMLArrayValues("PTSBenchmark/Settings/Option/Identifier");
	$settings_menu = $xml_parser->getXMLArrayValues("PTSBenchmark/Settings/Option/Menu");

	$USER_ARGS = "";
	$TEXT_ARGS = "";
	for($option_count = 0; $option_count < sizeof($settings_name); $option_count++)
	{
		$this_identifier = $settings_identifier[$option_count];
		echo "\n$settings_name[$option_count]:\n";

		if(!empty($settings_menu[$option_count]))
		{
			$xml_parser = new tandem_XmlReader($settings_menu[$option_count]);
			$option_names = $xml_parser->getXMLArrayValues("Entry/Name");
			$option_values = $xml_parser->getXMLArrayValues("Entry/Value");

			if(count($option_values) == 1)
			{
				$bench_choice = 1;
			}
			else
			{
				do
				{
					echo "\n";
					for($i = 0; $i < count($option_names); $i++)
					{
						echo ($i + 1) . ": " . $option_names[$i] . "\n";
					}
					echo "\nPlease Enter Your Choice: ";
					$bench_choice = strtolower(trim(fgets(STDIN)));
				}
				while($bench_choice < 1 || $bench_choice > count($option_names));
			}

			$TEXT_ARGS .= "$settings_name[$option_count]: " . $option_names[($bench_choice - 1)];
			$USER_ARGS .= $settings_argument[$option_count] . $option_values[($bench_choice - 1)] . " ";

			if($option_count < sizeof($settings_name) - 1)
				$TEXT_ARGS .= "; ";
		}
		else
		{
			echo "\nEnter Value: ";
			$value = strtolower(trim(fgets(STDIN)));
			$USER_ARGS .= $settings_argument[$option_count] . $value;
		}
	}
	unset($xml_parser);

	if($SAVE_RESULTS)
	{
		$xml_parser = new tandem_XmlReader(file_get_contents(XML_PROFILE_DIR . "$TO_RUN.xml"));
		$test_description = $xml_parser->getXMLValue("PTSBenchmark/Information/Description");
		$test_version = $xml_parser->getXMLValue("PTSBenchmark/PhoronixTestSuite/Version");
		$test_type = $xml_parser->getXMLValue("PTSBenchmark/PhoronixTestSuite/ApplicationType");
		$test_maintainer = $xml_parser->getXMLValue("PTSBenchmark/PhoronixTestSuite/Maintainer");
		unset($xml_parser);
	}
	pts_recurse_call_benchmark(array($TO_RUN), array($USER_ARGS), $SAVE_RESULTS, $RESULTS, $RESULTS_IDENTIFIER, array($TEXT_ARGS));
}
else if($TO_RUN_TYPE == "TEST_SUITE")
{
	echo pts_string_header(ucwords($TO_RUN) . " Test Suite");

	echo "\nRunning " . ucwords($TO_RUN) . " Test Suite...\n\n";

	$xml_parser = new tandem_XmlReader(file_get_contents(XML_SUITE_DIR . $TO_RUN . ".xml"));

	if($SAVE_RESULTS)
	{
		$test_description = $xml_parser->getXMLValue("PTSuite/PhoronixTestSuite/Description");
		$test_version = $xml_parser->getXMLValue("PTSuite/PhoronixTestSuite/Version");
		$test_type = $xml_parser->getXMLValue("PTSuite/PhoronixTestSuite/BenchmarkType");
		$test_maintainer = $xml_parser->getXMLValue("PTSuite/PhoronixTestSuite/Maintainer");
	}

	$suite_benchmarks = $xml_parser->getXMLArrayValues("PTSuite/PTSBenchmark/Benchmark");
	$arguments = $xml_parser->getXMLArrayValues("PTSuite/PTSBenchmark/Arguments");
	$arguments_description = $xml_parser->getXMLArrayValues("PTSuite/PTSBenchmark/Description");
	unset($xml_parser);

	pts_recurse_call_benchmark($suite_benchmarks, $arguments, $SAVE_RESULTS, $RESULTS, $RESULTS_IDENTIFIER, $arguments_description);
}
else if($SAVE_RESULTS && ($TO_RUN_TYPE == "GLOBAL_COMPARISON" || $TO_RUN_TYPE == "LOCAL_COMPARISON"))
{
	echo pts_string_header("Global Comparison Against: " . $TO_RUN);

	$xml_parser = new tandem_XmlReader(file_get_contents(SAVE_RESULTS_DIR . $TO_RUN . "/composite.xml"));
	$CUSTOM_TITLE = $xml_parser->getXMLValue("PhoronixTestSuite/Suite/Title");
	$test_description = $xml_parser->getXMLValue("PhoronixTestSuite/Suite/Description");
	$test_version = $xml_parser->getXMLValue("PhoronixTestSuite/Suite/Version");
	$test_type = $xml_parser->getXMLValue("PhoronixTestSuite/Suite/Type");
	$test_maintainer = $xml_parser->getXMLValue("PhoronixTestSuite/Suite/Maintainer");

	$suite_benchmarks = $xml_parser->getXMLArrayValues("PhoronixTestSuite/Benchmark/TestName");
	$arguments = $xml_parser->getXMLArrayValues("PhoronixTestSuite/Benchmark/TestArguments");
	$arguments_description = $xml_parser->getXMLArrayValues("PhoronixTestSuite/Benchmark/Attributes");
	unset($xml_parser);

	pts_recurse_call_benchmark($suite_benchmarks, $arguments, $SAVE_RESULTS, $RESULTS, $RESULTS_IDENTIFIER, $arguments_description);
}
else
{
	echo "\nUnrecognized option: $TO_RUN_TYPE\n";
}

if($SAVE_RESULTS)
{
	$test_notes = pts_process_running_string(array("Compiz", "Firefox", "Thunderbird"));

	if(defined("TEST_GRAPHICS"))
	{
		$aa_level = graphics_antialiasing_level();
		$af_level = graphics_anisotropic_level();

		if(!empty($aa_level) && !empty($af_level))
			$test_notes .= " \nAntialiasing: $aa_level Anisotropic Filtering: $af_level.";
	}

	// Power Saving Technologies?
	$cpu_savings = pts_processor_power_savings_enabled();
	if(!empty($cpu_savings))
	{
		$test_notes .= " \n" . $cpu_savings;
	}

	$cpu_mode = pts_report_power_mode();
	if(!empty($cpu_mode))
	{
		$test_notes .= " \n" . $cpu_mode;
	}

	$id = pts_request_new_id();
	$RESULTS->setXslBinding("pts-results-viewer.xsl");
	$RESULTS->addXmlObject("PhoronixTestSuite/System/Hardware", $id, pts_hw_string());
	$RESULTS->addXmlObject("PhoronixTestSuite/System/Software", $id, pts_sw_string());
	$RESULTS->addXmlObject("PhoronixTestSuite/System/Author", $id, pts_current_user());
	$RESULTS->addXmlObject("PhoronixTestSuite/System/TestDate", $id, date("F j, Y h:i A"));
	$RESULTS->addXmlObject("PhoronixTestSuite/System/TestNotes", $id, trim($test_notes));
	$RESULTS->addXmlObject("PhoronixTestSuite/System/Version", $id, PTS_VERSION);
	$RESULTS->addXmlObject("PhoronixTestSuite/System/AssociatedIdentifiers", $id, $RESULTS_IDENTIFIER);

	$id = pts_request_new_id();
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Title", $id, $CUSTOM_TITLE);
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Name", $id, $TO_RUN);
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Version", $id, $test_version);
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Description", $id, $test_description);
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Type", $id, $test_type);
	$RESULTS->addXmlObject("PhoronixTestSuite/Suite/Maintainer", $id, $test_maintainer);

	if($BENCHMARK_RAN)
	{
		pts_save_benchmark_file($PROPOSED_FILE_NAME, $RESULTS);
		echo "Results Saved To: " . SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml\n";
		display_web_browser(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml");

		$upload_results = pts_bool_question("Would you like to upload these results to PTS Global (Y/n)?", true, "UPLOAD_RESULTS");

		if($upload_results)
		{
			echo "\nTags are optional and used on PTS Global for making it easy to share, search, and organize test results. Example tags could be the type of test performed (i.e. WINE tests) or the hardware used (i.e. Dual Core SMP).\n\nEnter the tags you wish to provide (separated by commas): ";
			$tags_input = trim(preg_replace("/[^a-zA-Z0-9s, -]/", "", fgets(STDIN)));

			if(empty($tags_input))
			{
				// Auto tagging
				$tags_array = array();
				array_push($tags_array, $RESULTS_IDENTIFIER);

				switch(cpu_core_count())
				{
					case 1:
						array_push($tags_array, "Single Core");
						break;
					case 2:
						array_push($tags_array, "Dual Core");
						break;
					case 4:
						array_push($tags_array, "Quad Core");
						break;
					case 8:
						array_push($tags_array, "Octal Core");
						break;
				}

				$cpu_type = processor_string();

				if(strpos($cpu_type, "Intel") !== false)
					array_push($tags_array, "Intel");
				else if(strpos($cpu_type, "AMD") !== false)
					array_push($tags_array, "AMD");
				else if(strpos($cpu_type, "VIA") !== false)
					array_push($tags_array, "VIA");

				$gpu_type = graphics_processor_string();

				if(strpos($cpu_type, "ATI") !== false)
					array_push($tags_array, "ATI");
				else if(strpos($cpu_type, "NVIDIA") !== false)
					array_push($tags_array, "NVIDIA");

				if(kernel_arch() == "x86_64")
					array_push($tags_array, "64-bit Linux");

				$tags_input = implode(", ", $tags_array);
			}

			$upload_url = pts_global_upload_result(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml", $tags_input);

			if(!empty($upload_url))
			{
				echo "\nResults Uploaded To: " . $upload_url . "\n";
				display_web_browser($upload_url, "Do you want to launch PTS Global");
			}
		}

		echo "\n";
	}

	pts_monitor_update(); // Update sensors, etc
}

if(defined("SCREENSAVER_KILLED"))
	shell_exec("gconftool --type bool --set /apps/gnome-screensaver/idle_activation_enabled true 2>&1");

?>
