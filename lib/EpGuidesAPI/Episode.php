<?php

namespace EpGuidesAPI;

class Episode {

  /**
   * @var Show
   */
  private $show;
  private $title;
  private $is_special;
  private $season;
  private $episode;
  private $number;
  private $release_date;
  private $raw_data;

  public function __construct($show, $episode_data) {
    $this->raw_data = $episode_data;
    $this->show = $show;

    $this->title = $episode_data['title'];
    $this->is_special = (strtolower($episode_data['special']) == 'y') ? true : false;
    if ($this->is_special) {
      $this->season = null;
      $this->episode = null;
      $this->number = null;
    } else {
      $this->season = (is_numeric($episode_data['season'])) ? intval($episode_data['season']) : null;
      $this->episode = (is_numeric($episode_data['episode'])) ? intval($episode_data['episode']) : null;
      $this->number = (is_numeric($episode_data['number'])) ? intval($episode_data['number']) : null;
    }

    // CST timezone.
    $timezone = new \DateTimeZone('Canada/Saskatchewan');
    $date = \DateTime::createFromFormat('d/M/y H:i:s', $episode_data['airdate'] . ' 12:00:00', $timezone);
    $this->release_date = $date->getTimestamp();
    unset($date);
    unset($timezone);
  }

  public function getTitle() {
    return $this->title;
  }

  public function isSpecial() {
    return $this->is_special;
  }

  public function getSeason() {
    return $this->season;
  }

  public function getEpisode() {
    if ($this->is_special) {
      return false;
    }
    return $this->episode;
  }

  public function getNumber() {
    if ($this->is_special) {
      return false;
    }
    return $this->number;
  }

  public function getReleaseDate() {
    return $this->release_date;
  }

  public function getNextEpisode() {
    if ($this->is_special) {
      return false;
    }

    $episodes = $this->show->getEpisodes();
    foreach ($episodes as $episode) {
      if ($episode->getNumber() === ($this->number + 1)) {
        return $episode;
      }
    }

    return false;
  }

  public function getRawData() {
    return $this->raw_data;
  }
}
