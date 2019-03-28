/* Welcome to the mess that is PeptideAnnotator.js */
// TODO:
// Make it work

angular.module("IPSA.bulk.controller", []);
angular.module("IPSA.directive", []);

var myApp = angular.module('IPSA', ['ngAnimate', 'ngSanitize', 'ui.bootstrap', 'ui.select', 'ngHandsontable', "IPSA.bulk.controller",
    "IPSA.directive", "hc.downloader", 'minicolors', 'ngStorage']);

myApp.config(function(uiSelectConfig) {
  uiSelectConfig.theme = 'bootstrap';
});

/* master controller to carry similar functionality to $rootScope */
myApp.controller('MasterCtrl', function($scope, $uibModal, $log, $localStorage, $http, $element, $attrs, $transclude, $window) {
  $scope.ids = [];
  $scope.isProcessing = false;
  
  $scope.idObject = {
    ids: [],
    mods: [],
    selectedSequence: ""
  };

  $scope.formatOption = function(id) {
    // squash race condition where we try to format an id that hasn't loaded in yet
    if (typeof id == "undefined") {
    } else {
      var returnString = "";
      if (id.hasOwnProperty("sequence")) {
        returnString += id.sequence + "|#" + id.scanNumber + "|" + id.charge;
        if (id.mods) {
          returnString += "|" + id.mods;
        }
      }
      
      return returnString;
    }    
  };

  $scope.formatSelect = function() {
    var returnString = "";
    if ($scope.idObject.selectedSequence.hasOwnProperty("sequence")) {
      returnString += $scope.idObject.selectedSequence.sequence + "|#" + $scope.idObject.selectedSequence.scanNumber + "|" + $scope.idObject.selectedSequence.charge;
      if ($scope.idObject.selectedSequence.mods) {
        returnString += "|" + $scope.idObject.selectedSequence.mods;
      }
    }
    
    return returnString;
  };

  $scope.formatUploadedIdOption = function(id) {
    var returnString = "";
    
    if (id) {
      returnString += "<b>Peptide Sequence: </b>" + id.sequence + "<br/>" + 
          "<b>MS Scan Number: </b>" + id.scanNumber + "<br/>" + 
          "<b>Charge State: </b>" + id.charge;
      
      if (id.mods) {
        returnString +="<br/><b>Modifications: </b>" + id.mods;
      }
    }

    return returnString;
  };

  // get timestamp from localstorage. Retrieve info from database if it exists
  if (typeof $localStorage.timeStampIdentifier === "undefined") {
    alert("Make sure user uploads data first");
    $window.location.href = "BulkDataUpload.html";
  } else {
    var url = "support/php/RetrieveUploadedIdentifications.php";
    // send time stamp and file name as post data
    var data = {
      timeStamp: $localStorage.timeStampIdentifier
    };

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
        } else {
          if (typeof response.data.IDs == "undefined" || response.data.IDs.length == 0) {
              alert("Cannot find uploaded identifications. Either this data was not uploaded or it has expired. Please (re)upload your data to continue.");
              //$window.location.href = "BulkDataUpload.html";
          } else {
            $scope.ids = response.data.IDs;
            $scope.idObject.ids = response.data.IDs;
            $scope.idObject.mods = response.data.modifications;
          }
      }
    });
  }

  // stores peptide information
  $scope.peptide = {
    sequence: "TESTPEPTIDE",
    precursorCharge: 2,
    charge: 1,
    fragmentMin: 1,
    fragmentMax: 1,
    mods: []
  };

  // stores the values for selected fragments and colors
  $scope.checkModel = {
    a: { selected: false, color: "#820CAD", label: "a" },
    b: { selected: false, color: "#0D75BC", label: "b" },
    c: { selected: false, color: "#07A14A", label: "c" },
    C: { selected: false, color: "#035729", label: "c-1" },
    x: { selected: false, color: "#D1E02D", label: "x" },
    y: { selected: false, color: "#BE202D", label: "y" },
    z: { selected: false, color: "#F79420", label: "z\u2022" },
    Z: { selected: false, color: "#A16016", label: "z+1" },
    H2O: { selected: false },
    NH3: { selected: false },
    HPO3: { selected: false },
    CO2: { selected: false },
    precursor: { selected: true, color: "#666666"},
    unassigned: { selected: true, color: "#A6A6A6"}
  };

  $scope.checkResults = { 
    string: "unmatched", 
    colors: "#A6A6A6",
    colorArray: []
  };

  $scope.tableColumns = [
    {id: 1, name: 'M/Z, Intensity'},
    {id: 2, name: 'M/Z, Intensity, S/N'}
  ];

  $scope.db = {};

  $scope.db.columns = [
    {
      data:"mZ", 
      title :"Mass To Charge", 
      type: "numeric", 
      format: "0,0,0.0000" 
    },
    {
      data:"intensity", 
      title :"Intensity", 
      type: "numeric", 
      format: "0,0,0.0000" 
    }
  ];

  $scope.predeterminedMods = [];
  $scope.userMods = [];
  $scope.potentialMods = [];
  $scope.modObject = {};

  $scope.db.items = [];

  $scope.annotatedResults;

  $scope.$watch('idObject.selectedSequence', function () {
    if ($scope.idObject.selectedSequence.sequence) {
      $log.log($scope.idObject);
      $scope.peptide.sequence = $scope.idObject.selectedSequence.sequence;
      $scope.peptide.precursorCharge = $scope.idObject.selectedSequence.charge;

      var formattedMods = $scope.formatModificationsForProcessing();
      
      $scope.matchFormattedModsToDatabase(formattedMods);

      $scope.peptide.mods = formattedMods;
      if ($scope.peptide.precursorCharge > 0) {
        $scope.peptide.charge = 1;
        $scope.peptide.fragmentMin = 1;
        $scope.peptide.fragmentMax = $scope.peptide.precursorCharge - 1;
        $scope.checkModel.a.label = "a";
      } else {
        $scope.peptide.charge = -1;
        $scope.peptide.fragmentMin = $scope.peptide.precursorCharge + 1;
        $scope.peptide.fragmentMax = -1;
        $scope.checkModel.a.label = "a\u2022";
      }

      var url = "support/php/RetrieveUploadedSpectrum.php";
      // send time stamp and file name as post data
      var data = {
        timeStamp: $localStorage.timeStampIdentifier,
        scanNumber: $scope.idObject.selectedSequence.scanNumber
      };

      $http.post(url, data)
        .then( function(response) {
          if (response.data.hasOwnProperty("error")) {
            alert(response.data.error);
          } else {
            $scope.db.items = [];
            if (typeof response.data.mzs == "undefined" || response.data.mzs.length == 0) {
              alert("Uploaded data has expired after the allotted time. Please re-upload new files");
              $window.location.href = "BulkDataUpload.html";
            } else {
              for (let i = 0; i < response.data.mzs.length; i++) {
                $scope.db.items.push({mZ: parseFloat(response.data.mzs[i]), intensity: parseFloat(response.data.intensities[i])});
              }
            } 
          }
        });
    }
  }, true);

  var contains = function(a) {
    var isIn = false;
    for (var i = 0; i < $scope.mods.length; i++) {
      var listMod = $scope.mods[i];
      if (a.name == listMod.name) {
        if (a.site == listMod.site) {
          if (a.index == listMod.index) {
            if (a.hasOwnProperty("elementChange") && listMod.hasOwnProperty("elementChange")) {
              if (a.elementChange == listMod.elementChange) {
                isIn = true;
              }
            } else if (a.hasOwnProperty("deltaMass") && listMod.hasOwnProperty("deltaMass")) {
              if (a.deltaMass == listMod.deltaMass) {
                isIn = true;
              }
            }
          }
        }
      }
    }
    return isIn;
  }

  $scope.cutoffs = {
    matchingCutoff: 0,
    matchingType: "% Base Peak",
    toleranceType: "ppm",
    tolerance: 10
  };

  $scope.matchFormattedModsToDatabase = function(formattedMods) {
    formattedMods.forEach(function(formattedModification) {
      for (var i = 0; i < $scope.mods.length; i++) {
        if (formattedModification.name == $scope.mods[i].name) {
          if (formattedModification.index == $scope.mods[i].index) {
            if (formattedModification.deltaMass == $scope.mods[i].deltaMass) {
              $scope.peptide.mods.push($scope.mods[i]);
              break;
            }
          }
        }
      }
    });
  };

  $scope.swapToleranceType = function() {
    if ($scope.cutoffs.toleranceType === "Da") {
      $scope.cutoffs.toleranceType = "ppm";
      $scope.cutoffs.tolerance *= 1000
    } else {
      $scope.cutoffs.toleranceType = "Da";
      $scope.cutoffs.tolerance /= 1000
    }
  };

  $scope.swapMatchingType = function() {
    if ($scope.cutoffs.matchingType === "Intensity") {
      $scope.cutoffs.matchingType = "% Base Peak";
      $scope.cutoffs.matchingCutoff = 0;
    } else {
      $scope.cutoffs.matchingType = "Intensity";
      $scope.cutoffs.matchingCutoff = 0;
    }
  };

  $scope.validateTolerance = function() {
    if ($scope.cutoffs.tolerance < 0 || typeof $scope.cutoffs.tolerance == "undefined") {
      $scope.cutoffs.tolerance = 0;
    }
  };

  $scope.validateThreshold = function() {
    if ($scope.cutoffs.matchingCutoff < 0 || typeof $scope.cutoffs.matchingCutoff == "undefined") {
      $scope.cutoffs.matchingCutoff = 0;
    }
  };

  $scope.validateFragmentCharge = function() {
    if (!$scope.peptide.charge) {
      $scope.peptide.charge = 1;
    }

    $scope.peptide.charge = Math.round($scope.peptide.charge);

    // Check to make sure charge is set
    if (typeof $scope.peptide.charge == "undefined") {
      if ($scope.peptide.precursorCharge > 0) {
        $scope.peptide.charge = 1;
      } else {
        $scope.peptide.charge = -1;
      }
    }

    // make sure fragment charge is within max and min depending on precursor charge
    if ($scope.peptide.charge < $scope.peptide.fragmentMin) {
      $scope.peptide.charge = $scope.peptide.fragmentMin;
    } else if ($scope.peptide.charge > $scope.peptide.fragmentMax) {
      $scope.peptide.charge = $scope.peptide.fragmentMax;
    }
  }

  $scope.checkAlpha = function(string) {
    return /^[AC-IK-NP-TVWY]+$/i.test(string);
  };

  $scope.checkTerm = function(string) {
    return /^[cn]/i.test(string);
  };

  $scope.formatModificationsForProcessing = function() {
    if ($scope.idObject.selectedSequence.mods) {
      var formattedMods = [];
      var listedMods =  $scope.idObject.selectedSequence.mods.split(";");
      // ModName:index e.g. Oxidation:5
      listedMods.forEach(function(mod) {
        var splitMod = mod.split(":");

        var name = splitMod[0].trim();
        var pre_index = splitMod[1].toLowerCase();
        var index, deltaMass = 0;

        // checks if it modification is N or C terminal
        if ($scope.checkTerm(pre_index)) {
          if (pre_index.includes("n")) {
            index = 0;
          } else {
            index = $scope.idObject.selectedSequence.sequence.length;
          }
        } else {
          index = parseInt(pre_index) - 1;
        }
        $scope.idObject.mods.forEach(function(listMod) {
          if (listMod.name.trim() == name) {
            deltaMass = listMod.mass;
          }
        });

        formattedMods.push({
          name: name, 
          index: index, 
          deltaMass: deltaMass
        });
        
      });
      return formattedMods;
    } else {
      return [];
    }
  }

  $scope.loadMods = function() {
    // parse modifications from database only one time. 
    if ($scope.predeterminedMods.length == 0) {
      var url = "support/php/RetrieveUnimodifications.php";
      // send time stamp and file name as post data

      $http.post(url)
        .then( function(response) {
          if (response.data.hasOwnProperty("error")) {
            alert(response.data.error);
          } else {
            $scope.predeterminedMods = response.data;

            // retrieve custom user modifications
            url = "support/php/RetrieveUserModifications.php";
            var data = {
              timeStamp: $localStorage.timeStampIdentifier
            };
            
            $http.post(url, data)
              .then( function(response) {
                if (response.data.hasOwnProperty("error")) {
                  alert(response.data.error);
                } else {
                  $scope.userMods = response.data;

                  $scope.potentialMods = $scope.userMods.concat($scope.predeterminedMods);
                  $scope.mods = [];
                  for (var i = 0; i < $scope.peptide.sequence.length; i++) {
                    var char = $scope.peptide.sequence.charAt(i).toUpperCase();

                    for (var j = 0; j < $scope.potentialMods.length; j++) {
                      var mod = $scope.potentialMods[j];
                      var addMod = {};
                      if (mod.site.charAt(0) == char && mod.site != "N-terminus" && mod.site != "C-terminus") {
                        addMod = 
                        {
                          name: mod.name,
                          site: mod.site,
                          index: i,
                          deltaMass: mod.monoisotopicMassShift,
                          unimod: mod.unimodId
                        }
                        if (!contains(addMod)) {
                          $scope.mods.push(addMod);
                        }
                      } else if (mod.site == "N-terminus") {
                        if (mod.hasOwnProperty("monoisotopicMassShift")) {
                          addMod = 
                          {
                            name: mod.name,
                            site: mod.site,
                            index: -1,
                            deltaMass: mod.monoisotopicMassShift,
                            unimod: mod.unimodId
                          };
                        } 
                        if (!contains(addMod)) {
                          $scope.mods.push(addMod);
                        }
                      } else if (mod.site == "C-terminus") {
                        if (mod.hasOwnProperty("monoisotopicMassShift")) {
                          addMod = 
                          {
                            name: mod.name,
                            site: mod.site,
                            index: $scope.peptide.sequence.length,
                            deltaMass: mod.monoisotopicMassShift,
                            unimod: mod.unimodId
                          };
                          if (!contains(addMod)) {
                            $scope.mods.push(addMod);
                          }
                        }
                      }
                    }
                  }
                }
              });
          }
        });
    }
    // modifications are already loaded. just check which are valid with the peptide sequence.
    else {
      $scope.mods = [];
      for (var i = 0; i < $scope.peptide.sequence.length; i++) {
        var char = $scope.peptide.sequence.charAt(i).toUpperCase();

        for (var j = 0; j < $scope.potentialMods.length; j++) {
          var mod = $scope.potentialMods[j];
          var addMod = {};
          if (mod.site.charAt(0) == char && mod.site != "N-terminus" && mod.site != "C-terminus") {
            addMod = 
            {
              name: mod.name,
              site: mod.site,
              index: i,
              deltaMass: mod.monoisotopicMassShift,
              unimod: mod.unimodId
            }
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          } else if (mod.site == "N-terminus") {
            addMod = 
            {
              name: mod.name,
              site: mod.site,
              index: -1,
              deltaMass: mod.monoisotopicMassShift,
              unimod: mod.unimodId
            }; 
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          } else if (mod.site == "C-terminus") {
            addMod = 
            {
              name: mod.name,
              site: mod.site,
              index: $scope.peptide.sequence.length,
              deltaMass: mod.monoisotopicMassShift,
              unimod: mod.unimodId
            };
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          }
        }
      }
    }
  }

  $scope.$watchCollection('peptide.sequence', function () {
    $scope.loadMods();
  });

  $scope.$watchCollection('modObject', function () {
  });
});

myApp.controller('PeptideCtrl', function ($scope) {
  $scope.decrementCharge = function() {
    $scope.peptide.charge--;
  }

  $scope.incrementCharge = function() {
    $scope.peptide.charge++;
  }
});

myApp.controller('DataCtrl', ['$scope', function ($scope) {
    $scope.selectedFormat = $scope.tableColumns[0];      
}]);

myApp.controller('ModCtrl', ['$scope', '$log', function ($scope, $log) {
    $scope.modSelectOption = function(mod) {
      var returnString = mod.name + ": " + mod.site;

      if (mod.index != -1 && mod.index != $scope.peptide.sequence.length) {
        returnString += mod.index + 1 
      }

      returnString += " (";

      if (mod.hasOwnProperty("deltaMass")) {
        if (mod.deltaMass > 0) {
          returnString += "+";
        }
        returnString += mod.deltaMass + ")";
      } else {
        returnString += mod.elementChange + ")";
      }

      return returnString;
    };

    $scope.formatModificationOption = function(mod) {
      var returnString = "<b>Modification Category: </b>";

      if (mod.unimod) {
        returnString += mod.unimod + "<br/>";
      } else {
        returnString += "User Modification<br/>";
      }

      returnString += "<b>Modification Name: </b>" + mod.name + "<br/>" 
        + "<b>Modification Site: </b>"; 

      if (mod.site) {
        returnString += mod.site + "<br/>";
      } else {
        if (mod.index == 0) {
          returnString += "N-terminus<br/>";
        } else if (mod.index == $scope.peptide.sequence.length) {
          returnString += "C-terminus<br/>";
        } else {
          returnString += $scope.peptide.sequence[mod.index] + "<br/>";
        }
      }

      returnString += "<b>Modification Index: </b>" + (mod.index + 1) + "<br/>"
        + "<b>Modification Mass Shift: </b>" + parseFloat(mod.deltaMass).toFixed(4);

      return returnString;
    };
}]);

myApp.controller('ColorCtrl', function ($scope) {
  $scope.wheelsettings = {
          control: 'wheel',
          position: 'bottom left'
    };
});