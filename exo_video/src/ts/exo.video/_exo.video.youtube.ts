class ExoVideoYoutube extends ExoVideoBase {
  protected static apiState:number = 0;
  protected $video:JQuery;
  protected started:boolean = false;

  protected make() {
    super.make();
    ExoVideoYoutube.getApi().then(() => {
      this.videoBuild();
    });
  }

  protected videoBuild() {
    if (typeof this.player === 'undefined') {
      const parameters = {
        loop: 0,
        start: this.get('start'),
        autoplay: 0,
        controls: 0,
        disablekb: 1,
        showinfo: 0,
        playsinline: 1,
        wmode: 'transparent',
        iv_load_policy: 3,
        modestbranding: 1,
        rel: 0,
        fs: 0
      };
      this.player = new YT.Player(this.getId() + '-video', {
        height: '100%',
        width: '100%',
        playerVars: parameters,
        videoId: this.get('videoId'),
        events: {
          onReady: (e) => {
            this.$video.css('visibility', 'hidden');
            this.videoReady();
          },
          onStateChange: (e) => {
            if (e.data === 1 && this.started === false) {
              this.started = true;
              this.$video.css('transform', '');
              setTimeout(() => {
                this.$video.css('visibility', 'visible').hide().fadeIn();
              }, 3000);
              this.videoStartTimer();
            }
            if (e.data === 0 && this.get('loop')) {
              this.videoRewind();
              this.videoMute();
              this.videoPlay();
            } else if (e.data === 0) {
              // I need a consistant way to tell if the video is over
              const videoOverEvent = new CustomEvent('videoOverEvent', {
                detail: {
                  iFrameId: this.getId() + '-video'
                }
              });
              document.dispatchEvent(videoOverEvent);
            }
          }
        }
      });
    }
  }

  protected videoReady() {
    super.videoReady();
    this.videoPlay();
    this.videoWatch();
  }

  protected videoStartTimer() {
    Drupal.ExoVideo.onTimeUpdate(this);
    if (!this.get('loop') && this.videoTime().toFixed(2) > (this.player.getDuration().toFixed(2) - 0.4)) {
      this.$video.css('transform', '').fadeOut(400);
      this.$videoWrapper.addClass('loop-stop');
    }
    else {
      setTimeout(() => {
        this.videoStartTimer();
      }, 100);
    }
  }

  public videoTime() {
    return this.player.getCurrentTime();
  }

  protected videoPlay() {
    return this.player.playVideo();
  }

  protected videoPause() {
    return this.player.pauseVideo();
  }

  protected videoRewind() {
    return this.player.seekTo(0);
  }

  protected videoMute() {
    return this.player.mute();
  }

  protected videoUnMute() {
    return this.player.unMute();
  }

  public static getApi():Promise<void> {
    return new Promise((resolve, reject) => {
      if (ExoVideoYoutube.apiState === 0) {
        ExoVideoYoutube.apiState = 1;
        var tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        window.onYouTubeIframeAPIReady = () => {
          ExoVideoYoutube.apiState = 2;
          resolve();
          Drupal.Exo.$document.trigger('exo-video-youtube-ready');
        };
      }
      else if (ExoVideoYoutube.apiState === 1) {
        Drupal.Exo.$document.one('exo-video-youtube-ready', function () {
          resolve();
        });
      }
      else {
        resolve();
      }
    });
  }
}

Drupal.ExoVideoProviders['youtube'] = ExoVideoYoutube;
