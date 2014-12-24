<?php

namespace EpGuidesAPI;

class Show {
  private $epguides_name = null;
  private $epguides_id = null;
  private $title = null;
  private $imdb_id = null;
  private $data = array('page' => null, 'csv' => null);
  private $episodes = null;

  public function __construct($epguides_name) {
    $this->epguides_name = $epguides_name;
    $this->parseData();
  }

  private function parseData() {
    // Download show information page.
    $result = $this->downloadURL("http://epguides.com/$this->epguides_name/");
    $this->data['page'] = $result;
    unset($result);

    // Parse Title and IMDB ID.
    $subject = $this->data['page'];
    $pattern = '/<h1><a href="[\w:\/\/.]*title\/([\w.]*)">([\w\s.&:\']*)[\w)(]*<\/a>/';
    preg_match($pattern, $subject, $matches);
    unset($subject);
    unset($pattern);
    if ((!isset($matches[1])) || (!isset($matches[2]))) {
      throw new Exception('Unable to parse title and IMDB ID.');
    }
    $this->imdb_id = trim($matches[1]);
    $this->title = trim($matches[2]);
    unset($matches);

    // Parse EPGuides ID and CSV link.
    $subject = $this->data['page'];
    $pattern = '/<a href="(http:\/\/epguides\.com\/common\/exportToCSV\.asp\?rage=([\w]+))"/';
    preg_match($pattern, $subject, $matches);
    unset($subject);
    unset($pattern);
    if ((!isset($matches[1])) || (!isset($matches[2]))) {
      throw new Exception('Unable to parse EPGuides ID and CSV link.');
    }
    $csv_link = $matches[1];
    $this->epguides_id = $matches[2];
    unset($matches);

    // Download episodes list.
    $result = $this->downloadURL($csv_link);

    // Cleanup CSV.
    $subject = $result;
    $pattern = '/<pre>(.+)<\/pre>/s';
    preg_match($pattern, $subject, $matches);
    unset($subject);
    unset($pattern);
    if (!isset($matches[1])) {
      throw new Exception('Unable to parse CSV.');
    }
    $tmp_csv = trim($matches[1]);
    unset($result);
    unset($matches);

    // Parse CSV data.
    $tmp_csv = $this->parseCSV($tmp_csv);
    $this->data['csv'] = $tmp_csv;
    unset($tmp_csv);
  }

  private function parseCSV($CSVData) {
    $rows = explode("\n", $CSVData);

    $headers = array_shift($rows);
    $headers = str_getcsv($headers);
    foreach ($headers as &$header) {
      $header = trim($header, "? \t\n\r\0\x0B");
    }

    $parsed_rows = array();
    foreach ($rows as $row) {
      $csv_row = str_getcsv($row);
      $csv_row = array_combine($headers, array_values($csv_row));
      $parsed_rows[] = $csv_row;
      unset($csv_row);
    }
    unset($headers);
    unset($rows);

    return $parsed_rows;
  }

  private function downloadURL($url) {
    $options = array(
      CURLOPT_URL => $url,
      CURLOPT_HEADER => 0,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 4
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    if(!$result) {
      throw new Exception('CURL Error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
  }

  public function getTitle() {
    return $this->title;
  }

  public function getIMDB() {
    return $this->imdb_id;
  }

  public function getEpisodes() {
    if ($this->episodes !== null) {
      return $this->episodes;
    }

    $episodes = array();
    foreach ($this->data['csv'] as $episode_data) {
      $episode = new Episode($this, $episode_data);
      $episodes[] = $episode;
    }

    $this->episodes = $episodes;
    return $episodes;
  }
}
