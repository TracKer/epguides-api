<?php

namespace EpGuidesAPI;

class Show {
  private $epguides_name;
  private $epguides_id;
  private $title;
  private $imdb_id;
  private $raw_data = array('page' => null, 'csv' => null, 'printable' => null);
  private $episodes = array();
  private $remove_specials;
  private $remove_other;
  private $printable_episodes;

  /**
   * Constructor
   *
   * @param string $epguides_name
   *   Machine name of tv show can be taken from url on epguides.com.
   * @param array $options
   *   Additional options, like:
   *   - 'remove_specials': Flag which removes Specials from episodes list, default is true.
   *   - 'remove_other': Flag which removes other stuff from episodes list, default is true.
   */
  public function __construct($epguides_name, $options) {
    // Merge in defaults.
    $options += array(
      'remove_specials' => true,
      'remove_other' => true,
    );

    $this->epguides_name = $epguides_name;
    $this->remove_specials = $options['remove_specials'];
    $this->remove_other = $options['remove_other'];
    $this->parseData();
  }

  private function parseData() {
    // Download show information page.
    $result = $this->downloadURL("http://epguides.com/$this->epguides_name/");
    $this->raw_data['page'] = $result;
    unset($result);

    // Parse Title and IMDB ID.
    $subject = $this->raw_data['page'];
    $pattern = '/<h1><a href="[\w:\/\/.]*title\/([\w.]*)">([\w\s.&:\']*)[\w)(]*<\/a>/';
    preg_match($pattern, $subject, $matches);
    unset($subject);
    unset($pattern);
    if ((!isset($matches[1])) || (!isset($matches[2]))) {
      throw new \Exception('Unable to parse title and IMDB ID.');
    }
    $this->imdb_id = trim($matches[1]);
    $this->title = trim($matches[2]);
    unset($matches);

    // Parse EPGuides ID and CSV link.
    $subject = $this->raw_data['page'];
    $pattern = '/<a href="(http:\/\/epguides\.com\/common\/exportToCSV\.asp\?rage=([\w]+))"/';
    preg_match($pattern, $subject, $matches);
    unset($subject);
    unset($pattern);
    if ((!isset($matches[1])) || (!isset($matches[2]))) {
      throw new \Exception('Unable to parse EPGuides ID and CSV link.');
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
      throw new \Exception('Unable to parse CSV.');
    }
    $tmp_csv = trim($matches[1]);
    unset($result);
    unset($matches);

    // Parse CSV data.
    $tmp_csv = $this->parseCSV($tmp_csv);
    $this->raw_data['csv'] = $tmp_csv;
    unset($tmp_csv);

    // If "Remove other" flag is set, then we have to download printable
    // version from tvrage.com.
    if ($this->remove_other) {
      $this->raw_data['printable'] = $this->downloadURL("http://www.tvrage.com/shows/id-$this->epguides_id/printable");

      $subject = $this->raw_data['printable'];
      $pattern = '/>[\d]+ :([\d]{2})x([\d]{2}) - (.*)<\//U';
      preg_match_all($pattern, $subject, $matches);
      unset($subject);
      unset($pattern);
      if (empty($matches[1])) {
        throw new \Exception('Unable to parse printable version from tvrage.com.');
      }
      $this->printable_episodes = array();
      foreach ($matches[1] as $key => $tmp_item) {
        $season = intval($matches[1][$key]);
        $episode = intval($matches[2][$key]);
        $title = trim($matches[3][$key]);
        $this->printable_episodes[$season][$episode] = $title;
      }
      dump($this->printable_episodes);
      unset($matches);
    }
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
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 4,
      CURLOPT_FOLLOWLOCATION => true,
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    if(!$result) {
      throw new \Exception('CURL Error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
  }

  public function getEpGuidesName() {
    return $this->epguides_name;
  }

  public function getEpGuidesID() {
    return $this->epguides_id;
  }

  public function getTitle() {
    return $this->title;
  }

  public function getRawData() {
    return $this->raw_data;
  }

  public function getIMDB() {
    return $this->imdb_id;
  }

  /**
   * Get array of episodes sorted by release date.
   *
   * @return Episode[]
   *   Array of Episodes.
   */
  public function getEpisodes() {
    if (!empty($this->episodes)) {
      return $this->episodes;
    }

    $episodes = array();
    foreach ($this->raw_data['csv'] as $episode_data) {
      $episode = new Episode($this, $episode_data);

      // Remove Specials.
      if ($this->remove_specials && $episode->isSpecial()) {
        unset($episode);
        continue;
      }

      // Remove other.
      if ($this->remove_specials && (!isset($this->printable_episodes[$episode->getSeason()][$episode->getEpisode()]))) {
        unset($episode);
        continue;
      }

      $episodes[] = $episode;
    }

    // Sort episodes by release date.
    uasort($episodes, array($this, 'sorterCallback'));
    $episodes = array_values($episodes);

    $this->episodes = $episodes;
    return $episodes;
  }

  private function sorterCallback(Episode $ep1, Episode $ep2) {
    if ($ep1->getReleaseDate() == $ep2->getReleaseDate()) {
      if ($ep1->isSpecial() || $ep2->isSpecial()) {
        // If one of episodes is special - do nothing.
        return 0;
      }
      // If time is equal, then compare seasons.
      if ($ep1->getSeason() == $ep2->getSeason()) {
        // If seasons are equal, then compare episodes.
        if ($ep1->getEpisode() == $ep2->getEpisode()) {
          // If episodes are equal - do nothing, since "number" is not based on
          // time, so it's not representative.
          return 0;
        }
        return ($ep1->getEpisode() < $ep2->getEpisode()) ? -1 : 1;
      }
      return ($ep1->getSeason() < $ep2->getSeason()) ? -1 : 1;
    }
    return ($ep1->getReleaseDate() < $ep2->getReleaseDate()) ? -1 : 1;
  }
}
