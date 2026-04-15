(function(){
  if (!window.WWW_VT) return;

  var config = window.WWW_VT;
  var mainSource = config.src;
  var adsEnabled = parseInt(config.ads_enabled, 10) === 1;
  var adsInterval = parseInt(config.ads_interval_seconds || 0, 10);
  var adsDurationLimit = parseInt(config.ads_duration_seconds || 0, 10);
  var videoDurationLimit = parseInt(config.video_duration_seconds || 0, 10);
  var nextAdThreshold = (adsEnabled && adsInterval > 0) ? adsInterval : Infinity;
  var isAdPlaying = false;
  var pendingAd = null;
  var resumeTime = 0;
  var resumePaused = false;
  var adRequestInFlight = false;
  var seenAds = new Set();

  var video = null;
  var mainHls = null;
  var usingNativeHls = false;
  var adOverlay = null;
  var adOverlayTimer = null;
  var adLastTime = 0;

  function ensureAdOverlay(){
    if(!video) return;
    if(adOverlay) return;
    var parent = video.parentNode;
    try {
      if(parent && typeof window.getComputedStyle === 'function'){
        var pos = window.getComputedStyle(parent).position;
        if(pos === 'static'){
          parent.style.position='relative';
        }
      }
    } catch(e){}
    adOverlay=document.createElement('div');
    adOverlay.className='www-vt-ad-overlay';
    adOverlay.style.position='absolute';
    adOverlay.style.top='10px';
    adOverlay.style.right='10px';
    adOverlay.style.background='rgba(0,0,0,0.75)';
    adOverlay.style.color='#fff';
    adOverlay.style.padding='8px 12px';
    adOverlay.style.borderRadius='4px';
    adOverlay.style.fontSize='14px';
    adOverlay.style.zIndex='5';
    adOverlay.style.pointerEvents='none';
    adOverlay.style.opacity='0';
    adOverlay.style.transition='opacity .2s ease';
    if(parent){
      parent.appendChild(adOverlay);
    }else{
      document.body.appendChild(adOverlay);
    }
  }

  function showAdOverlay(){
    ensureAdOverlay();
    if(adOverlay){
      adOverlay.style.opacity='1';
    }
  }

  function hideAdOverlay(){
    if(adOverlay){
      adOverlay.style.opacity='0';
      adOverlay.textContent='';
    }
    if(adOverlayTimer){
      clearInterval(adOverlayTimer);
      adOverlayTimer=null;
    }
  }

  function updateAdOverlayCountdown(force){
    if(!isAdPlaying) return;
    ensureAdOverlay();
    if(!adOverlay) return;
    var remaining=0;
    var hasCountdown=false;
    var current=Math.max(0, Math.floor(video && !isNaN(video.currentTime) ? video.currentTime : 0));
    if(pendingAd && typeof pendingAd.duration!=='undefined'){
      var parsed=parseInt(pendingAd.duration,10);
      if(!isNaN(parsed) && parsed>0){
        remaining=Math.max(0, Math.ceil(parsed - current));
        hasCountdown=true;
      }
    }
    if(!hasCountdown && adsDurationLimit>0){
      remaining=Math.max(0, Math.ceil(adsDurationLimit - current));
      hasCountdown=true;
    }
    var message='Pubblicità';
    if(hasCountdown){
      if(remaining<=0){
        remaining=1;
      }
      message='Pubblicità – riprende tra '+remaining+' s';
    }
    adOverlay.textContent=message;
    if(force){
      showAdOverlay();
    }
  }

  function blockSeekDuringAd(e){
    if(!isAdPlaying || !video) return;
    if(e && typeof e.preventDefault==='function'){e.preventDefault();}
    try{
      video.currentTime = adLastTime || 0;
    }catch(err){}
    return false;
  }

  var hlsScript=document.createElement('script');
  hlsScript.src='https://cdn.jsdelivr.net/npm/hls.js@latest';
  hlsScript.onload=init;
  document.head.appendChild(hlsScript);

  function supportsNativeHls(video){
    return !!video && typeof video.canPlayType==='function' && video.canPlayType('application/vnd.apple.mpegurl');
  }

  function init(){
    video=document.getElementById('www-vt-video');
    if(!video || !window.WWW_VT) return;
    if(init._initialized){
      if(window.Hls && !init._hlsAttached){
        attachPlayback();
      }
      return;
    }
    if(!window.Hls && !supportsNativeHls(video)){
      return;
    }
    init._initialized=true;
    setupTracking();
    attachPlayback();
  }

  function attachPlayback(){
    if(window.Hls && Hls.isSupported() && !supportsNativeHls(video)){
      usingNativeHls=false;
      mainHls=new Hls({
        autoStartLoad:false,
        lowLatencyMode:true,
        maxBufferLength:10,
        maxMaxBufferLength:30,
        startPosition:-1,
        fragLoadingTimeOut:10000,
        xhrSetup:function(xhr){xhr.withCredentials=true;},
      });
      mainHls.loadSource(mainSource);
      mainHls.attachMedia(video);
      video.addEventListener('play', function onPlay(){
        if(!isAdPlaying){
          mainHls.startLoad();
        }
        video.removeEventListener('play', onPlay);
      });
      init._hlsAttached=true;
    } else {
      usingNativeHls=true;
      video.src=mainSource;
      init._hlsAttached=true;
    }
  }

  function setupTracking(){
    var productId=config.product_id||0;
    var restBase=(config.rest||'').replace(/\/$/,'');
    var heartbeatMs=parseInt(config.heartbeat_ms||10000,10);
    var positionSaveMs=parseInt(config.position_save_ms||60000,10);
    var watched=new Set();
    var lastSecond=-1;
    var heartbeatTimer=null;
    var positionTimer=null;
    var token=config.token||'';
    var sessionKey='www_vt_watched_'+(token?token.split('.')[1]:Math.random().toString(36).slice(2));
    var adFallbackTimer=null;
    var overlayTickMs=500;

    try{
      var stored=JSON.parse(localStorage.getItem(sessionKey)||'[]');
      stored.forEach(function(sec){watched.add(sec);});
    }catch(e){}

    if(adsEnabled && adsInterval>0){
      var multiples=Math.floor(watched.size/adsInterval);
      nextAdThreshold=(multiples+1)*adsInterval;
      if(nextAdThreshold<=watched.size){
        nextAdThreshold=watched.size+adsInterval;
      }
    } else {
      nextAdThreshold=Infinity;
    }

    fetchPosition().then(function(pos){
      if(typeof pos!=='number' || isNaN(pos)){pos=0;}
      if(video.readyState>=1){
        try{video.currentTime=pos;}catch(e){}
      }else{
        video.addEventListener('loadedmetadata', function applyStart(){
          try{video.currentTime=Math.min(pos, Math.floor(video.duration||pos));}catch(e){}
        }, {once:true});
      }
    });

    video.addEventListener('timeupdate', function(){
      if(isAdPlaying){
        adLastTime=Math.max(adLastTime, Math.floor(video.currentTime||0));
        updateAdOverlayCountdown(false);
        return;
      }
      var current=Math.floor(video.currentTime||0);
      if(current<0) return;
      if(current!==lastSecond){
        watched.add(current);
        lastSecond=current;
        if(watched.size % 10 === 0){
          try{localStorage.setItem(sessionKey, JSON.stringify(Array.from(watched)));}catch(e){}
        }
        maybeTriggerAd();
      }
    });

    function serializePayload(){
      return JSON.stringify({
        watched_seconds: watched.size,
        position_seconds: Math.floor(video.currentTime||0),
        product_id: productId,
        video_duration_seconds: videoDurationLimit
      });
    }

    function sendProgress(force){
      if(!force && isAdPlaying) return;
      var payload=serializePayload();
      fetch(restBase+'/view/progress', {
        method:'POST',
        credentials:'include',
        headers:{'Content-Type':'application/json'},
        body:payload
      }).catch(function(){});
      try{localStorage.setItem(sessionKey, JSON.stringify(Array.from(watched)));}catch(e){}
    }

    function startTimers(){
      if(isAdPlaying) return;
      if(!heartbeatTimer && heartbeatMs>0){
        heartbeatTimer=setInterval(sendProgress, heartbeatMs);
      }
      if(!positionTimer && positionSaveMs>0){
        positionTimer=setInterval(sendProgress, positionSaveMs);
      }
    }

    function stopTimers(){
      if(heartbeatTimer){clearInterval(heartbeatTimer);heartbeatTimer=null;}
      if(positionTimer){clearInterval(positionTimer);positionTimer=null;}
    }

    async function fetchPosition(){
      try{
        var response=await fetch(restBase+'/view/position?product_id='+encodeURIComponent(productId),{
          credentials:'include'
        });
        if(!response.ok) return 0;
        var json=await response.json();
        return json && typeof json.position_seconds==='number' ? json.position_seconds : 0;
      }catch(e){
        return 0;
      }
    }

    function scheduleNextBreak(base){
      if(!adsEnabled || adsInterval<=0){
        nextAdThreshold=Infinity;
        return;
      }
      var current=typeof base==='number'?base:watched.size;
      nextAdThreshold=current+adsInterval;
    }

    function maybeTriggerAd(){
      if(!adsEnabled || adsInterval<=0) return;
      if(isAdPlaying || adRequestInFlight) return;
      if(watched.size < nextAdThreshold) return;
      requestAd();
    }

    function requestAd(){
      adRequestInFlight=true;
      scheduleNextBreak(watched.size);
      var url=restBase+'/ads/list?product_id='+encodeURIComponent(productId);
      if(seenAds.size){
        url+='&exclude='+encodeURIComponent(Array.from(seenAds).join(','));
      }
      fetch(url, {
        credentials:'include'
      }).then(function(resp){
        if(!resp.ok) return null;
        return resp.json();
      }).then(function(json){
        if(!json || !json.ad || !json.ad.src){
          if(window.console && typeof console.debug==='function'){
            console.debug('WWW_VT','Nessuna ADS disponibile per la sessione');
          }
          scheduleNextBreak(watched.size);
          return;
        }
        if(json.ad.id && seenAds.has(json.ad.id)){
          scheduleNextBreak(watched.size);
          return;
        }
        startAd(json.ad);
      }).catch(function(){}).finally(function(){
        adRequestInFlight=false;
      });
    }

    function clearAdFallback(){
      if(adFallbackTimer){
        clearTimeout(adFallbackTimer);
        adFallbackTimer=null;
      }
    }

    function markAdViewed(ad){
      if(!ad || !ad.id) return;
      seenAds.add(ad.id);
      fetch(restBase+'/ads/mark', {
        method:'POST',
        credentials:'include',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          ad_id: ad.id,
          product_id: productId
        })
      }).catch(function(){});
    }

    function startAd(ad){
      if(!ad || !ad.src) return;
      if(ad.id && seenAds.has(ad.id)){
        scheduleNextBreak(watched.size);
        return;
      }
      pendingAd=ad;
      resumeTime=video.currentTime||0;
      resumePaused=video.paused;
      isAdPlaying=true;
      sendProgress(true);
      stopTimers();
      try{video.pause();}catch(e){}
      clearAdFallback();
      adLastTime=0;
      video.controls=false;
      video.addEventListener('seeking', blockSeekDuringAd, true);
      updateAdOverlayCountdown(true);
      if(adOverlayTimer){clearInterval(adOverlayTimer);}
      adOverlayTimer=setInterval(function(){updateAdOverlayCountdown(false);}, overlayTickMs);

      if(mainHls && !usingNativeHls && window.Hls && Hls.isSupported()){
        var onAdManifest=function(){
          if(mainHls && mainHls.off){
            mainHls.off(Hls.Events.MANIFEST_PARSED, onAdManifest);
          }
          try{video.currentTime=0;}catch(e){}
          try{video.play();}catch(e){}
        };
        if(mainHls && mainHls.on){
          mainHls.on(Hls.Events.MANIFEST_PARSED, onAdManifest);
        }
        mainHls.stopLoad();
        mainHls.loadSource(ad.src);
        mainHls.startLoad();
      } else {
        video.src=ad.src;
        video.load();
        video.addEventListener('loadedmetadata', function handleAdLoad(){
          video.removeEventListener('loadedmetadata', handleAdLoad);
          try{video.currentTime=0;}catch(e){}
          try{video.play();}catch(e){}
        });
      }

      var fallbackSeconds=0;
      if(ad && typeof ad.duration!=='undefined'){
        var parsedDuration=parseInt(ad.duration,10);
        if(!isNaN(parsedDuration) && parsedDuration>0){
          fallbackSeconds=parsedDuration;
        }
      }
      if(fallbackSeconds<=0 && adsDurationLimit>0){
        fallbackSeconds=adsDurationLimit;
      }
      if(fallbackSeconds>0){
        adFallbackTimer=setTimeout(function(){
          if(isAdPlaying){
            try{video.pause();}catch(e){}
            resumeMainPlayback();
          }
        }, fallbackSeconds*1000);
      }
    }

    function resumeMainPlayback(){
      if(!isAdPlaying){
        return;
      }
      clearAdFallback();
      var ad=pendingAd;
      pendingAd=null;
      markAdViewed(ad);
      video.removeEventListener('seeking', blockSeekDuringAd, true);
      video.controls=true;
      hideAdOverlay();
      if(mainHls && !usingNativeHls && window.Hls && Hls.isSupported()){
        var onMainManifest=function(){
          if(mainHls && mainHls.off){
            mainHls.off(Hls.Events.MANIFEST_PARSED, onMainManifest);
          }
          try{video.currentTime=resumeTime;}catch(e){}
          if(!resumePaused){
            try{video.play();}catch(e){}
          }
        };
        if(mainHls && mainHls.on){
          mainHls.on(Hls.Events.MANIFEST_PARSED, onMainManifest);
        }
        mainHls.stopLoad();
        mainHls.loadSource(mainSource);
        mainHls.startLoad();
      } else {
        video.src=mainSource;
        video.load();
        video.addEventListener('loadedmetadata', function restoreMain(){
          video.removeEventListener('loadedmetadata', restoreMain);
          try{video.currentTime=resumeTime;}catch(e){}
          if(!resumePaused){
            try{video.play();}catch(e){}
          }
        });
      }
      isAdPlaying=false;
      adLastTime=0;
      if(!resumePaused){
        startTimers();
      }
    }

    video.addEventListener('play', function(){
      if(!isAdPlaying){
        startTimers();
      }
    });

    video.addEventListener('pause', function(){
      if(!isAdPlaying){
        sendProgress();
        stopTimers();
      }
    });

    video.addEventListener('ended', function(){
      if(isAdPlaying){
        clearAdFallback();
        resumeMainPlayback();
        return;
      }
      sendProgress();
      stopTimers();
      try{localStorage.removeItem(sessionKey);}catch(e){}
    });

    window.addEventListener('beforeunload', function(){
      try{localStorage.setItem(sessionKey, JSON.stringify(Array.from(watched)));}catch(e){}
      if(isAdPlaying) return;
      var payload=serializePayload();
      if(navigator.sendBeacon){
        try{navigator.sendBeacon(restBase+'/view/progress', new Blob([payload], {type:'application/json'}));}catch(e){}
      } else {
        var xhr=new XMLHttpRequest();
        try{
          xhr.open('POST', restBase+'/view/progress', false);
          xhr.setRequestHeader('Content-Type','application/json');
          xhr.send(payload);
        }catch(e){}
      }
    });
  }
})();
