angular.module("IPSA.spectrum.controller", []);
angular.module("IPSA.directive", []);

var myApp = angular.module('IPSA', ['ngAnimate', 'ngSanitize', 'ui.bootstrap', 'ui.select', 'ngHandsontable', "IPSA.spectrum.controller",
    "IPSA.directive", "hc.downloader", 'minicolors', 'ngStorage']);

myApp.config(function(uiSelectConfig) {
  uiSelectConfig.theme = 'bootstrap';
});

/* master controller to carry similar functionality to $rootScope */
myApp.controller('MasterCtrl', function($scope, $uibModal, $log, $localStorage, $http, $element, $attrs, $transclude) {
  
  // stores peptide information
  $scope.peptide = {
    sequence: "TESTPEPTIDE",
    precursorCharge: 2,
    charge: 1,
    fragmentMin: 1,
    fragmentMax: 1
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

  $scope.db.items = [];

  $scope.annotatedResults;

  $scope.predeterminedMods = [];
  $scope.userMods = [];
  $scope.potentialMods = [];

  $scope.$watchCollection('peptide.sequence', function () {
    $scope.loadMods();
  });

  $scope.$watchCollection('userMods', function () {
    $scope.loadMods();
  });

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

  $scope.loadMods = function() {
    // get potential mods from text file
    if ($scope.predeterminedMods.length == 0) {
      var text = 'Mods not yet loaded.';
      $http.get('support/mods/Modifications.txt') 
        .then(function (data) {
          text = data.data.split("\r\n");
          var tempArray = [];

          for (var i = 0; i < text.length; i++) {
            var line = text[i].split(';');

            tempArray.push(
              {
                name: line[0],
                site: line[1],
                elementChange: line[2]
              }
            );
          }
          $scope.predeterminedMods = tempArray;

          // now that we have predetermined mods, get user mods
          $scope.userMods = $localStorage.userMods;
          if (typeof $scope.userMods === "undefined") {
            $scope.userMods = [];
          }

          $scope.potentialMods = $scope.userMods.concat($scope.predeterminedMods);

          $scope.mods = [];
          for (var i = 0; i < $scope.peptide.sequence.length; i++) {
            var char = $scope.peptide.sequence.charAt(i).toUpperCase();

            for (var j = 0; j < $scope.potentialMods.length; j++) {
              var mod = $scope.potentialMods[j];
              var addMod = {};
              if (mod.site.charAt(0) == char && mod.site != "N-terminus" && mod.site != "C-terminus") {
                
                if (mod.hasOwnProperty("deltaMass")) {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: i,
                    deltaMass: mod.deltaMass
                  };
                } else {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: i,
                    elementChange: mod.elementChange
                  };
                }
                
                if (!contains(addMod)) {
                  $scope.mods.push(addMod);
                }
              } else if (mod.site == "N-terminus") {
                if (mod.hasOwnProperty("deltaMass")) {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: -1,
                    deltaMass: mod.deltaMass
                  };
                } else {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: -1,
                    elementChange: mod.elementChange
                  };
                }
                if (!contains(addMod)) {
                  $scope.mods.push(addMod);
                }
              } else if (mod.site == "C-terminus") {
                if (mod.hasOwnProperty("deltaMass")) {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: $scope.peptide.sequence.length,
                    deltaMass: mod.deltaMass
                  };
                } else {
                  addMod = 
                  {
                    name: mod.name,
                    site: mod.site,
                    index: $scope.peptide.sequence.length,
                    elementChange: mod.elementChange
                  };
                }
                if (!contains(addMod)) {
                  $scope.mods.push(addMod);
                }
              }
            }
          }
        }, function (error) {
          alert('Error: Modification file not found');
        });
    } else {
      // now that we have predetermined mods, get user mods
      $scope.userMods = $localStorage.userMods;
      if (typeof $scope.userMods === "undefined") {
        $scope.userMods = [];
      }

      $scope.potentialMods = $scope.userMods.concat($scope.predeterminedMods);

      $scope.mods = [];
      for (var i = 0; i < $scope.peptide.sequence.length; i++) {
        var char = $scope.peptide.sequence.charAt(i);

        for (var j = 0; j < $scope.potentialMods.length; j++) {
          var mod = $scope.potentialMods[j];
          var addMod = {};
          if (mod.site.charAt(0) == char && mod.site != "N-terminus" && mod.site != "C-terminus") {
            
            if (mod.hasOwnProperty("deltaMass")) {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: i,
                deltaMass: mod.deltaMass
              };
            } else {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: i,
                elementChange: mod.elementChange
              };
            }
            
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          } else if (mod.site == "N-terminus") {
            if (mod.hasOwnProperty("deltaMass")) {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: -1,
                deltaMass: mod.deltaMass
              };
            } else {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: -1,
                elementChange: mod.elementChange
              };
            }
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          } else if (mod.site == "C-terminus") {
            if (mod.hasOwnProperty("deltaMass")) {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: $scope.peptide.sequence.length,
                deltaMass: mod.deltaMass
              };
            } else {
              addMod = 
              {
                name: mod.name,
                site: mod.site,
                index: $scope.peptide.sequence.length,
                elementChange: mod.elementChange
              };
            }
            if (!contains(addMod)) {
              $scope.mods.push(addMod);
            }
          }
        }
      }
    }
  }

  $scope.mods = [];

  $scope.modObject = {};

  $scope.openModal = function () {
    $uibModal.open({
      templateUrl: 'support/html/ModalTemplateNew.html',
      scope: $scope,
      controller: function ($scope, $uibModalInstance, $localStorage) {
        // add the mod to potential mods

        $scope.ok = function () {
          // validate input. if okay, then add to potential mods. save to web storage
          // is a name filled out in the modal
          if ($scope.potentialUserMod.name) {
            if ($scope.userModSites.selected.length > 0) {
              if ($scope.potentialUserMod.deltaMass != 0) {
                
                $scope.userModSites.selected.forEach(function(site) {
                  $scope.userMods.push({
                    name: $scope.potentialUserMod.name,
                    site: site,
                    deltaMass: $scope.potentialUserMod.deltaMass
                  });
                });
                
                $localStorage.userMods = $scope.userMods;
                $uibModalInstance.close();
              } else {
                alert("Please enter a modification mass shift. This can be positive or negative");
              }
            } else {
              alert("Please select at least one modification site");
            }
          } else {
            alert("Please give your modification a name.");
          }

        };
        
        $scope.clearMods = function() {
          if (confirm("Are you sure you want to clear all user mods from your history? This cannot be undone.")) {
            $localStorage.userMods = [];
            $scope.loadMods();
          }
        };

        // clear all user mod fields
        $scope.cancel = function () {
          $scope.potentialUserMod.name = "";
          $scope.potentialUserMod.site = "";
          $scope.potentialUserMod.deltaMass = 0;

          $uibModalInstance.dismiss('cancel');
        };


      }
      //Squash unhandled rejection on backdrop click that's thrown
      //TODO
      //Sorry future debugger
    }).result.then(function(){}, function(result){})
  };

  $scope.potentialUserMod = 
    {
      name: "",
      site: "",
      deltaMass: 0
    }

  $scope.cutoffs = {
    matchingCutoff: 0,
    matchingType: "% Base Peak",
    toleranceType: "ppm",
    tolerance: 10
  };

  $scope.validateSequence = function() {
    var regex = new RegExp("[AC-IK-NP-TVWY]", "i");
    for (var i = 0; i < $scope.peptide.sequence.length; i++) {

      var character = $scope.peptide.sequence[i];

      if (!regex.exec(character)) {
        i--;
        
        $scope.peptide.sequence = $scope.peptide.sequence.replace(character, "");
        alert(character + " is not a valid amino acid. Only the 20 canonical amino acids are supported.");
      }
    }
  }

  $scope.swapToleranceType = function() {
    if ($scope.cutoffs.toleranceType === "Da") {
      $scope.cutoffs.toleranceType = "ppm";
      $scope.cutoffs.tolerance *= 1000
    } else {
      $scope.cutoffs.toleranceType = "Da";
      $scope.cutoffs.tolerance /= 1000
    }
  }

  $scope.swapMatchingType = function() {
    if ($scope.cutoffs.matchingType === "Intensity") {
      $scope.cutoffs.matchingType = "% Base Peak";
      $scope.cutoffs.matchingCutoff = 0;
    } else if ($scope.cutoffs.matchingType === "% Base Peak") {
      $scope.cutoffs.matchingType = "S/N";
      $scope.cutoffs.matchingCutoff = 0;
    } else {
      $scope.cutoffs.matchingType = "Intensity";
      $scope.cutoffs.matchingCutoff = 0;
    }
  }

  // precursor charge
  $scope.validateCharge = function() {
    if (!$scope.peptide.precursorCharge) {
      $scope.peptide.precursorCharge = 2;
    }

    $scope.peptide.precursorCharge = Math.round($scope.peptide.precursorCharge);

    // Check to make sure charge is set
    if (typeof $scope.peptide.charge == "undefined") {
      $scope.peptide.precursorCharge = 2;
      $scope.peptide.charge = 1;
      // positive mode
    }

    // check to make sure precursor charge is a valid value (not -1, 0, 1) 
    if ($scope.peptide.precursorCharge == 0 || $scope.peptide.precursorCharge == 1) {
      $scope.peptide.precursorCharge = 2;
      $scope.peptide.charge = 1;
    } else if ($scope.peptide.precursorCharge == -1) {
      $scope.peptide.precursorCharge = -2;
      $scope.peptide.charge = -1;
    }

    // set fragment charge min and max from the precursor charge
    if ($scope.peptide.precursorCharge > 0) {
      $scope.peptide.fragmentMax = $scope.peptide.precursorCharge - 1;
      $scope.peptide.fragmentMin = 1;
      $scope.checkModel.a.label = "a";
    } else {
      $scope.peptide.fragmentMax = -1;
      $scope.peptide.fragmentMin = $scope.peptide.precursorCharge + 1;
      $scope.checkModel.a.label = "a\u2022";
    }
    $log.log($scope.peptide.precursorCharge);

    // validate fragment charge
    $scope.validateFragmentCharge();
  }

  // fragment charge validator
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
    $log.log($scope.peptide.charge);
  }

  $scope.checkAlpha = function(string) {
    return /^[AC-IK-NP-TVWY]+$/i.test(string);
  }

  $scope.userModSites = {
    selected: [],
    sites: [
      "N-terminus",
      "A",
      "C",
      "D",
      "E",
      "F",
      "G",
      "H",
      "I",
      "K",
      "L",
      "M",
      "N",
      "P",
      "Q",
      "R",
      "S",
      "T",
      "V",
      "W",
      "Y",
      "C-terminus"
    ]
  };

});

myApp.controller('PeptideCtrl', function ($scope) {

  $scope.decrementCharge = function() {
    $scope.peptide.charge--;
  }

  $scope.incrementCharge = function() {
    $scope.peptide.charge++;
  }

});

//controller for generating data paste dropdown and handsonTable
myApp.directive("handsontabletest", function() {
  
  return{
    templateUrl: 'support/html/HotTableTemplate.html',
    controller: function($scope, $element, $attrs, $transclude, $log) {
      
      $scope.afterInit = function() {
        $scope.handsonTableInstance = this;
      };

      $scope.db.items = [];
      
      $scope.handleFormat = function(item) {
        $scope.selectedFormat = item;
        if ($scope.selectedFormat.id == 1) {
          $scope.db.columns = [];
          $scope.db.columns.push({data:"mZ", title :"Mass To Charge", type: "numeric", format: "0,0,0.0000"});
          $scope.db.columns.push({data:"intensity", title :"Intensity", type: "numeric", format: "0,0,0.0000"});
        } 
        else {
          $scope.db.columns = [];
          $scope.db.columns.push({data:"mZ", title :"Mass To Charge", type: "numeric", format: "0,0,0.0000"});
          $scope.db.columns.push({data:"intensity", title :"Intensity", type: "numeric", format: "0,0,0.0000"});
          $scope.db.columns.push({data:"sN", title :"Signal To Noise Ratio", type: "numeric", format: "0,0,0.0000"});
        }
      };

      $scope.reset = function() {
        $scope.db.items = [];
      };
    }
  };
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
}]);

myApp.controller('ColorCtrl', function ($scope) {
  $scope.wheelsettings = {
          control: 'wheel',
          position: 'bottom left'
    };
});