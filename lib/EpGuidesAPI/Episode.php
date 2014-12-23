<?php

namespace EpGuidesAPI;

class Episode {

  /**
   * @var Show
   */
  private $show;
  private $season;
  private $episode;
  private $number;
  private $title;
  private $release_date;

  public function __construct($show, $episode_data) {
    $this->show = $show;
    if ($episode_data['special'] == 'y') {
      $this->season = 'special';
      $this->episode = null;
      $this->number = null;
    } else {
      $this->season = intval($episode_data['season']);
      $this->episode = intval($episode_data['episode']);
      $this->number = intval($episode_data['number']);
    }
    $this->title = $episode_data['title'];
    $this->release_date = $episode_data['airdate'];
  }

  public function getSeason() {
    return $this->season;
  }

  public function getEpisode() {
    if ($this->season == 'special') {
      return false;
    }
    return $this->episode;
  }

  public function getNumber() {
    if ($this->season == 'special') {
      return false;
    }
    return $this->number;
  }

  public function getNextEpisode() {
    if ($this->season == 'special') {
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
}
