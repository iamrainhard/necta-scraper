<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

class NectaController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function findStudent(Request $request)
    {
        // Extract combined parameters from request
        $combinedParams = $request->indexNo;
//        dd($combinedParams);

        // Split combined parameters into individual parameters
        list($schoolNumber, $studentNumber, $year) = explode('/', $combinedParams);


        // Extract parameters from request
        $year = (int)$year;
        $examType = strtolower("csee");
        $schoolNumber = strtolower($schoolNumber);
//        $studentNumber = (int) $studentNumber;
        if ($examType !== 'csee') {
            return response()->json(['error' => 'Invalid exam type or year'], 400);
        }
        $url = $this->constructUrl($year, $examType, $schoolNumber);

//        dd($url);
        if (!$url) {
            return response()->json(['error' => 'Invalid exam type or year'], 400);
        }

        try {
            // Initialize HttpBrowser
            $client = new HttpBrowser(HttpClient::create());

            // Navigate to URL
            $crawler = $client->request('GET', $url);

            // Check if the request was successful
            if ($client->getResponse()->getStatusCode() !== 200) {
                throw new \Exception("Failed to connect to server\nError code {$client->getResponse()->getStatusCode()}");
            }
            // Fetch school name
            $schoolName = $this->getSchoolName($url);
//            dd($schoolName);
            // Initialize parsing logic
            $studentData = [
                'examination_number' => strtoupper("{$schoolNumber}/{$studentNumber}"),
                'year_of_exam' => $year,
                'exam_type' => $examType,
                'gender' => '*',
                'school_name' => $schoolName,
                'division' => '*',
                'points' => '*',
                'subjects' => [],
            ];

            $found = false;
            $index = $this->getIndex($schoolNumber, $year);
            $studentsTable = $crawler->filter('table')->eq($index);


            // Iterate over table rows and columns to find student data
            $studentsTable->filter('tr')->each(function ($tr) use (&$found, &$studentData) {
                $row = $tr->filter('td')->each(function ($td) {
                    return $td->text();
                });

                if ($row[0] === $studentData['examination_number']) {
                    $studentData['gender'] = $row[1];
                    $studentData['division'] = $row[3];
                    $studentData['points'] = $row[2];
                    $studentData['subjects'] = $this->splitAfter($row[4]);
                    $found = true;
                }
            });

            if (!$found) {
                throw new \Exception("Wrong Examination Number {$studentData['examination_number']}");
            } else {
                return response()->json([
                    'status' => 'success',
                    'data' => $studentData,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function splitAfter($data)
    {
        // Implement your splitAfter function logic here
        // For example:
        return explode(',', $data);
    }

    public function getIndex($schoolNumber, $year)
    {
        $index = 0;

        if (strpos($schoolNumber, 'p') === 0) {
            if ($year > 2019) {
                $index = 1;
            } else {
                $index = 0;
            }
        } else {
            if ($year > 2019) {
                $index = 2;
            } else {
                $index = 0;
            }
        }

        return $index;
    }

    public function constructUrl($year, $examType, $schoolNumber)
    {
        $baseUrl = "";

        if ($examType === "acsee") {
            if ($year === 2023) {
                $baseUrl = "https://matokeo.necta.go.tz/results/2023/acsee/results/{$schoolNumber}.htm";
            } else {
                $baseUrl = "https://onlinesys.necta.go.tz/results/{$year}/acsee/results/{$schoolNumber}.htm";
            }
        } elseif ($examType === "csee") {
            if ($year === 2024) {
                $baseUrl = "https://matokeo.necta.go.tz/results/2024/csee/CSEE2024/CSEE2024/results/{$schoolNumber}.htm";
            } elseif ($year === 2021) {
                $baseUrl = "https://onlinesys.necta.go.tz/results/2021/csee/results/{$schoolNumber}.htm";
            } elseif ($year > 2014) {
                $baseUrl = "https://onlinesys.necta.go.tz/results/{$year}/csee/results/{$schoolNumber}.htm";
            } else {
                $baseUrl = "https://onlinesys.necta.go.tz/results/{$year}/csee/{$schoolNumber}.htm";
            }
        }

        return $baseUrl;
    }

    public function getSchoolName($url)
    {
        $schoolName = "";
        try {
            $client = new HttpBrowser(HttpClient::create());
            $crawler = $client->request('GET', $url);
            $nodes = $crawler->filter('font')->eq(0)->text();
//            dd($nodes);
            // Extract the school name between "RESULTS" and "DIV"
            $startPosition = strpos($nodes, 'RESULTS') + strlen('RESULTS');
            $endPosition = strpos($nodes, 'DIV', $startPosition);

            if ($startPosition !== false && $endPosition !== false) {
                $schoolName = trim(substr($nodes, $startPosition, $endPosition - $startPosition));

                // Remove the first word
                $schoolNameParts = explode(' ', $schoolName, 2);
                $schoolName = $schoolNameParts[1] ?? '';
            } else {
                $schoolName = '';
            }
//            $words = explode(' ', $nodes);


        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
        return $schoolName;
    }
}