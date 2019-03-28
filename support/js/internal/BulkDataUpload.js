/* Welcome to the second mess that is DataUpload.js */
// TODO:
// Make it work

var myApp = angular.module('IPSA.upload', ['ngAnimate', 'ngSanitize', 'ui.bootstrap', 'ngFileUpload', 'ngStorage']);

myApp.controller('MyCtrl', ['$scope', 'Upload', '$timeout', '$log', '$http', '$localStorage', function ($scope, Upload, $timeout, $log, $http, $localStorage) {

  $scope.files = {
  	identifications: 
  	{
  		file: {},
  		classType: "",
  		isValid: false,
  		isProcessing: false,
      errorMsg: "No Valid Data Found",
      confirmMsg: "",
      exampleEntry: null
  	},
  	peakList: 
  	{
  		file: {},
  		classType: "",
  		isValid: false,
  		isProcessing: false,
      errorMsg: "No Valid Data Found",
      confirmMsg: "",
      exampleEntry: null
  	},
  	modifications: 
  	{
  		file: {},
  		classType: "",
  		isValid: false,
  		isProcessing: false,
      errorMsg: "No Valid Data Found",
      confirmMsg: "",
      exampleEntry: null
  	} 
  };

  $scope.identifications = [];
  $scope.modifications = [];

  // define function to check for old uploaded data
  $scope.checkForPastValidData = function() {

    if (typeof $localStorage.timeStampIdentifier === "undefined") {
      $localStorage.timeStampIdentifier =(new Date()).getTime();
    } 

    var url = "support/php/CheckUploadedData.php";
    // send time stamp and file name as post data
    var data = {
      timeStamp: $localStorage.timeStampIdentifier,
      fileName: $scope.files.identifications.file.name
    };

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
        } else {
          if (response.data.results.hasIds) {
            let url = "support/php/RetrieveFirstEntry.php";
            let data = {
              timeStamp: $localStorage.timeStampIdentifier,
              target: "id"
            };

            $http.post(url, data)
              .then( function(response) {
                if (response.data.hasOwnProperty("error")) {
                  alert(response.data.error);
                } else {
                  $scope.files.identifications.exampleEntry = response.data;
              }
            });

            $scope.files.identifications.isValid = true;
            $scope.files.identifications.confirmMsg = "Previously uploaded data found";
            $scope.identifications = response.data.results.ids;
          }
          if (response.data.results.hasSpectra) {
            let url = "support/php/RetrieveFirstEntry.php";
            let data = {
              timeStamp: $localStorage.timeStampIdentifier,
              target: "spectrum"
            };

            $http.post(url, data)
              .then( function(response) {
                if (response.data.hasOwnProperty("error")) {
                  alert(response.data.error);
                } else {
                  $scope.files.peakList.exampleEntry = response.data;

                  // extract example mz and intensity to prevent data overrun
                  $scope.files.peakList.exampleEntry.mz = $scope.files.peakList.exampleEntry.mz.split(";")[0];
                  $scope.files.peakList.exampleEntry.intensity = $scope.files.peakList.exampleEntry.intensity.split(";")[0];
              }
            });

            $scope.files.peakList.isValid = true;
            $scope.files.peakList.confirmMsg = "Previously uploaded data found";
          }
          if (response.data.results.hasMods) {
            let url = "support/php/RetrieveFirstEntry.php";
            let data = {
              timeStamp: $localStorage.timeStampIdentifier,
              target: "mod"
            };

            $http.post(url, data)
              .then( function(response) {
                if (response.data.hasOwnProperty("error")) {
                  alert(response.data.error);
                } else {
                  $scope.files.modifications.exampleEntry = response.data;
                }
            });

            $scope.files.modifications.isValid = true;
            $scope.files.modifications.confirmMsg = "Previously uploaded data found";
          }
        }
    });
  };

  $scope.checkForPastValidData();

  function formatBytes(bytes) {
    if(bytes < 1024) return bytes + " Bytes";
    else if(bytes < 1048576) return(bytes / 1024).toFixed(1) + " KB";
    else if(bytes < 1073741824) return(bytes / 1048576).toFixed(1) + " MB";
    else return(bytes / 1073741824).toFixed(1) + " GB";
	};

  $scope.updateOldData = function(nextFunction) {
    // take old timestamp. save timestamp as previous timestamp. query all tables and update any uploaded data with new timestamp
    $localStorage.oldTimeStampIdentifier = $localStorage.timeStampIdentifier;
    $localStorage.timeStampIdentifier = (new Date()).getTime();

    var url = "support/php/UpdateOldData.php";
    // send time stamp and file name as post data
    var data = {
      timeStamp: $localStorage.timeStampIdentifier,
      oldTimeStamp: $localStorage.oldTimeStampIdentifier
    };

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error); 
        }
        nextFunction();
      });
  };

  $scope.uploadIdentifications = function(file) {
  	$scope.files.identifications.isProcessing = true;
    $scope.files.identifications.file = file;

    $scope.files.identifications.classType = "progress-striped active";

    if (file) {
      $scope.files.identifications.isValid = false;
      $scope.files.modifications.isValid = false;
      $scope.files.peakList.isValid = false;
      file.upload = Upload.upload({
          url: "support/php/UploadHandler.php",
          method: 'POST',
          file: file,
          data: {
          	awesomeThings: "Here",
          	targetPath: 'support/Upload Folder/'
          }
      }).then(function (response) {
      	$scope.updateOldData($scope.processIdentifications);
        $timeout(function () {
            file.result = response.data;
        });
      }, function (response) {
          if (response.status > 0)
              $scope.files.identifications.errorMsg = response.status + ': ' + response.data;
      }, function (evt) {
        file.progress = Math.min(100, Math.round(100.0 * evt.loaded / evt.total));
        file.loaded = formatBytes(evt.loaded) + "/" + formatBytes(evt.total);
        if (file.progress == 100) {
        	$scope.files.identifications.classType = "progress-striped";
        }
      });
    } else {
      $scope.files.identifications.isProcessing = false;
    }  
  };

  $scope.processIdentifications = function() {
  	var url = "support/php/processIdentifications.php";
    $scope.files.modifications.file.progress = -1;
    $scope.files.peakList.file.progress = -1;
  	// send time stamp and file name as post data
  	var data = {
  		timeStamp: $localStorage.timeStampIdentifier,
  		fileName: $scope.files.identifications.file.name
  	};

    // see what file type the uploaded doc is
    var isMzTab = data.fileName.search(/.mztab/i);
    var isMzIdentML = data.fileName.search(/.mzid/i);

    if (isMzTab != -1) {
      console.log("file is mztab");
      url = "support/php/processMzTab.php";
    } else if (isMzIdentML != -1) {
      console.log("file is mzIdentML");
      url = "support/php/processMzIdentML.php";
    }

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
          $scope.files.identifications.isProcessing = false;
        } else {
          $log.log(response.data);
          let url = "support/php/RetrieveFirstEntry.php";
          let data = {
            timeStamp: $localStorage.timeStampIdentifier,
            target: "id"
          };

          $http.post(url, data)
            .then( function(response) {
              if (response.data.hasOwnProperty("error")) {
                alert(response.data.error);
                $scope.files.identifications.isProcessing = false;
              } else {
                $log.log(response.data);
                $scope.files.identifications.exampleEntry = response.data;
            }
          });
          $scope.files.identifications.isValid = true;
          $scope.files.identifications.isProcessing = false;
          $scope.identifications = response.data;
          $scope.files.identifications.confirmMsg = "Upload Successful";
        }
      });
  };

  $scope.uploadPeakList = function(file) {
  	$scope.files.peakList.isProcessing = true;
  	$scope.files.peakList.file = file;
    $scope.files.peakList.classType = "progress-striped active";

    if (file) {
      $scope.files.peakList.isValid = false;
      $scope.files.modifications.isValid = false;
      file.upload = Upload.upload({
        url: "support/php/UploadHandler.php",
        method: 'POST',
        file: file,
        data: {
        	targetPath: 'support/Upload Folder/'
        }
      }).then(function (response) {
        $log.log("Upload");
        $log.log(response);
      	$scope.updateOldData($scope.processPeakList);
        $timeout(function () {
          file.result = response.data;
        });
      }, function (response) {
          if (response.status > 0) {
          	$scope.files.peakList.errorMsg = response.status + ': ' + response.data;
          }
      }, function (evt) {
        file.progress = Math.min(100, Math.round(100.0 * evt.loaded / evt.total));
        file.loaded = formatBytes(evt.loaded) + "/" + formatBytes(evt.total);
        if (file.progress == 100) {
        	$scope.files.peakList.classType = "progress-striped";
        }
      });
    } else {
      $scope.files.peakList.isProcessing = false;
    }  
  };

  $scope.processPeakList = function() {
    var url = "";
    if ($scope.files.peakList.file.name.split(".")[1].toUpperCase() === "MGF") {
      url = "support/php/ProcessMgf.php";
    } else {
      url = "support/php/ProcessMzml.php";
    }
    $scope.files.modifications.isValid = false;
    $scope.files.modifications.file.progress = -1;
  	// send time stamp and file name as post data
  	var data = {
  		timeStamp: $localStorage.timeStampIdentifier,
  		fileName: $scope.files.peakList.file.name,
  		validScans: []
  	};

  	$scope.identifications.forEach(function(ID) {
  		data.validScans.push(ID.scanNumber);
  	});

    data.validScans.sort(function (a, b) { return a - b; });

    $http.post(url, data)
      .then( function(response) {
        $log.log(response);
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
        } else {
          let url = "support/php/RetrieveFirstEntry.php";
          let data = {
            timeStamp: $localStorage.timeStampIdentifier,
            target: "spectrum"
          };

          $http.post(url, data)
            .then( function(response) {

              if (response.data.hasOwnProperty("error")) {
                alert(response.data.error);
                $scope.files.peakList.isProcessing = false;
              } else {
                $scope.files.peakList.exampleEntry = response.data;

                // extract example mz and intensity to prevent data overrun
                $scope.files.peakList.exampleEntry.mz = $scope.files.peakList.exampleEntry.mz.split(";")[0];
                $scope.files.peakList.exampleEntry.intensity = $scope.files.peakList.exampleEntry.intensity.split(";")[0];
                $scope.files.peakList.isProcessing = false;
                $scope.files.peakList.isValid = true;
                $scope.files.peakList.confirmMsg = "Upload Successful";
              }
          });
        }
    });
  };

  $scope.uploadMods = function(file) {
  	$scope.files.modifications.isProcessing = true;
  	$scope.files.modifications.file = file;

    $scope.files.modifications.classType = "progress-striped active";

    if (file) {
      $scope.files.modifications.isValid = false;
      file.upload = Upload.upload({
          url: "support/php/UploadHandler.php",
          method: 'POST',
          file: file,
          data: {
          	awesomeThings: "Here",
          	targetPath: 'support/Upload Folder/'
          }
      }).then(function (response) {
      	$scope.updateOldData($scope.processModifications);
      	
        $timeout(function () {
            file.result = response.data;
        });
      }, function (response) {
          if (response.status > 0)
              $scope.files.modifications.errorMsg = response.status + ': ' + response.data;
      }, function (evt) {
        file.progress = Math.min(100, Math.round(100.0 * evt.loaded / evt.total));
        file.loaded = formatBytes(evt.loaded) + "/" + formatBytes(evt.total);
        if (file.progress == 100) {
        	$scope.files.modifications.classType = "progress-striped";
        }
      });
    } else {
      $scope.files.modifications.isProcessing = false;
    }     
  };

  $scope.processModifications = function() {
  	var url = "support/php/ProcessModifications.php";
  	// send time stamp and file name as post data
  	var data = {
  		timeStamp: $localStorage.timeStampIdentifier,
  		fileName: $scope.files.modifications.file.name,
  	};

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
          $scope.files.modifications.isProcessing = false;
        } else {
          let url = "support/php/RetrieveFirstEntry.php";
          let data = {
            timeStamp: $localStorage.timeStampIdentifier,
            target: "mod"
          };

          $http.post(url, data)
            .then( function(response) {
              if (response.data.hasOwnProperty("error")) {
                alert(response.data.error);
                $scope.files.modifications.isProcessing = false;
              } else {
                $scope.files.modifications.exampleEntry = response.data;
            }
          });

        	$scope.files.modifications.isProcessing = false;
        	$scope.modifications = response.data;
        	$scope.files.modifications.isValid = true;
          $scope.files.modifications.confirmMsg = "Upload Successful";
        }
    });
  };
}]);