angular.module("IPSA.bulk.controller").controller("GraphCtrl", ["$scope", "$log", "$http", "$localStorage", function($scope, $log, $http, $localStorage) {
  var populateMods = function() {
    var returnArray = [];
    for (var i = 0; i < 13; i++) {
      returnArray.push({
        site: i - 1,
        deltaElement: 0,
        deltaMass: 0
      });
    }
    return returnArray;
  }

  $scope.set = {
    plotData: 
    {
      x: [ ],
      y: [ ],
      color: [ ],
      label: [ ],
      labelCharge: [ ],
      neutralLosses: [ ],
      barwidth: [ ],
      massError: [ ],
      theoMz: [ ],
      percentBasePeak: [ ],
      TIC: 0
    },
    peptide: 
    {
      scanNumber: 0,
      sequence: "TESTPEPTIDE", 
      precursorMz: 609.77229, 
      precursorCharge: $scope.peptide.precursorCharge,
      mods: populateMods()
    },
    settings: 
    {
      toleranceThreshold: 0,
      toleranceType: "",
      ionizationMode: ""
    }
  };

  $scope.n = 150;
  
  $scope.min = 0;
  
  $scope.max = 100;

  $scope.randomize = function() {
    _.times(150, function(n) {
      var x = _.random(0, 2000);
      var y = _.random(0, 100);
      if (y < 1) {
        y = 1;
      }
      $scope.set.plotData.TIC += y;
      $scope.set.plotData.y.push(y);
      $scope.set.plotData.x.push(x);
      $scope.set.plotData.color.push("#A6A6A6");
      $scope.set.plotData.label.push("");
      $scope.set.plotData.labelCharge.push(0);
      $scope.set.plotData.neutralLosses.push("");
      $scope.set.plotData.barwidth.push(1);
      $scope.set.plotData.massError.push("");
      $scope.set.plotData.theoMz.push(0);
      $scope.set.plotData.percentBasePeak.push(100 * y/d3.max($scope.set.plotData.y));
    });

    $scope.set.plotData.x.sort(function(a, b){return a - b});
    $scope.set.settings.toleranceType = "ppm";
    $scope.set.settings.toleranceThreshold = 10;
    $scope.set.settings.ionizationMode = "+";
  };

  $scope.plotData = function(returnedData) {
    $scope.set.peptide = 
    {
      scanNumber: $scope.idObject.selectedSequence.scanNumber,
      sequence: returnedData.sequence, 
      precursorMz: returnedData.precursorMz, 
      precursorCharge: $scope.peptide.precursorCharge,
      mods: returnedData.modifications
    };

    $scope.set.settings = 
    {
      toleranceThreshold: $scope.cutoffs.tolerance,
      toleranceType: $scope.cutoffs.toleranceType,
      ionizationMode: ""
    };

    if ($scope.peptide.precursorCharge > 0) {
      $scope.set.settings.ionizationMode = "+";
    } else {
      $scope.set.settings.ionizationMode = "-";
    }
    
    $scope.set.plotData.x = [ ];
    $scope.set.plotData.y = [ ];
    $scope.set.plotData.color = [ ];
    $scope.set.plotData.label = [ ];
    $scope.set.plotData.labelCharge = [ ];
    $scope.set.plotData.neutralLosses = [ ];
    $scope.set.plotData.barwidth = [ ];
    $scope.set.plotData.massError = [ ];
    $scope.set.plotData.theoMz = [ ];
    $scope.set.plotData.percentBasePeak = [ ];
    $scope.set.plotData.TIC = 0;
    
    returnedData.peaks.forEach(function(data) {
      $scope.set.plotData.x.push(data.mz);
      $scope.set.plotData.y.push(data.intensity);
      $scope.set.plotData.TIC += data.intensity;
      $scope.set.plotData.percentBasePeak.push(data.percentBasePeak);
      if (data.matchedFeatures.length == 0) {
        $scope.set.plotData.color.push($scope.colorArray[9]);
        $scope.set.plotData.label.push("");
        $scope.set.plotData.labelCharge.push(0);
        $scope.set.plotData.neutralLosses.push("");
        $scope.set.plotData.barwidth.push(1);
        $scope.set.plotData.massError.push("");
        $scope.set.plotData.theoMz.push(0);
      } else {
        var peakData = data.matchedFeatures[0];
        var fragment = peakData.feature;
        if (fragment.type == "a") {
          $scope.set.plotData.color.push($scope.colorArray[0]);
        } else if (fragment.type == "b") {
          $scope.set.plotData.color.push($scope.colorArray[1]);
        } else if (fragment.type == "c") {
          $scope.set.plotData.color.push($scope.colorArray[2]);
        } else if (fragment.type == "C") {
          $scope.set.plotData.color.push($scope.colorArray[3]);
        } else if (fragment.type == "x") {
          $scope.set.plotData.color.push($scope.colorArray[4]);
        } else if (fragment.type == "y") {
          $scope.set.plotData.color.push($scope.colorArray[5]);
        } else if (fragment.type == "z") {
          $scope.set.plotData.color.push($scope.colorArray[6]);
        } else if (fragment.type == "Z") {
          $scope.set.plotData.color.push($scope.colorArray[7]);
        } else if (fragment.type == "M") {
          $scope.set.plotData.color.push($scope.colorArray[8]);
        }

        if (fragment.neutralLoss == null) {
          $scope.set.plotData.neutralLosses.push("");
        } else {
          $scope.set.plotData.neutralLosses.push(fragment.neutralLoss);
        }
        
        $scope.set.plotData.labelCharge.push(fragment.charge);
        // two label types, precursor, or regular label w/wo neutral losses
        if (fragment.hasOwnProperty("isPrecursor")) {
          $scope.set.plotData.label.push("[" + fragment.type + fragment.number + "]");
        } else {
          $scope.set.plotData.label.push(fragment.type + fragment.number);
        }

        $scope.set.plotData.barwidth.push(3);
        $scope.set.plotData.massError.push(peakData.massError);
        $scope.set.plotData.theoMz.push(fragment.mz);
      }
    });

    $log.log($scope.set);
  };

  $scope.processData = function() {
    var url = "";
    if ($scope.peptide.precursorCharge > 0) {
      url = "support/php/processData.php";
    } else {
      url = "support/php/NegativeModeProcessData.php";
    }

    let submitData;

    if ($scope.invalidColors()) {

    } else {
      // only send over relevant data for processing
      if ($scope.db.items[0].hasOwnProperty('sN')){
        submitData = $scope.db.items.map(({ mZ, intensity, sN }) => ({ mZ, intensity, sN }));
      } else {
        submitData = $scope.db.items.map(({ mZ, intensity }) => ({ mZ, intensity }));
      }

      var charge = 0;
      if ($scope.peptide.precursorCharge > 0) {
        charge = $scope.peptide.charge + 1;
      } else {
        charge = $scope.peptide.charge;
      }

      var data = {
        "sequence" : $scope.peptide.sequence,
        "precursorCharge": $scope.peptide.precursorCharge,
        "charge" : charge,
        "fragmentTypes" : $scope.checkModel,
        "peakData" : submitData,
        "mods" : $scope.peptide.mods,
        "toleranceType" : $scope.cutoffs.toleranceType,
        "tolerance" : $scope.cutoffs.tolerance,
        "matchingType": $scope.cutoffs.matchingType,
        "cutoff": $scope.cutoffs.matchingCutoff
      };

      $scope.submittedData = data;

      $http.post(url, data)
        .then( function(response) {
          // if errors exist, alert user
          if (response.data.hasOwnProperty("error")) {
            alert(response.data.error);
          } else {
            $log.log(response.data);
            $scope.annotatedResults = response.data;
            $scope.plotData($scope.annotatedResults);
          }
      });
    }
  };

  $scope.invalidColors = function() {
    $scope.colorArray = [];

    // Add colors to array if selected and valid
    angular.forEach($scope.checkModel, function (value, key) {
      if (key !== "H2O" && key !== "NH3" && key !== "HPO3" && key !== "CO2") {  
        if (!$scope.checkHex(value.color)) {
          alert("Invalid color HEX code for selected fragment: " + key);
          return true;
        } else {
          if (value.selected) {
            $scope.colorArray.push(value.color);
          } else {
            $scope.colorArray.push("");
          }
        }
      }
    });

    return false;
  }

  $scope.checkHex = function(value) {
    return /(^#[0-9A-F]{6}$)|(^#[0-9A-F]{3}$)/i.test(value)
  }

  $scope.downloadData = function() {
    var csvRows = [];
    $log.log($scope.peptide);
    // write CV peptide sequence header
    csvRows.push("Scan Number, Sequence, Theoretical Mz, Charge, Modifications <Name;Index;Mass Change>, # Matched Fragments, # Bonds Broken, % TIC Explained");
    csvRows.push($scope.idObject.selectedSequence.scanNumber + "," + 
        $scope.set.peptide.sequence + "," + 
        d3.format("0.4f")($scope.set.peptide.precursorMz) + "," + 
        $scope.set.peptide.precursorCharge + "," + 
        $scope.formatModsForDownload() + "," + 
        $scope.getNumberFragments() + "," + 
        $scope.getFragmentedBonds() + "," + 
        $scope.getPercentTicExplained());
    
    csvRows.push("");

    // matched fragments headers
    csvRows.push("Fragment Type, Fragmented Bond Number, Attached Modifications <Name;Index;Mass Change>, Neutral Loss, Fragment Charge, Intensity, Experimental Mz, Theoretical Mz, " +
      "Mass Error (" + $scope.cutoffs.toleranceType + "), % Base Peak, % TIC");

    var fragments = $scope.formatMatchedFragmentRow();

    fragments.forEach(function(fragment) {
      csvRows.push(fragment);
    });

    var outputString = csvRows.join("\n");
    var a = document.createElement('a');

    a.href = 'data:attachment/csv,' +  encodeURIComponent(outputString);
    a.download = $scope.set.peptide.sequence + "_Data.csv";
    document.body.appendChild(a);

    a.click();
    a.remove();
  }

  $scope.getNumberFragments = function() {
    var numFragments = 0;
    $scope.set.plotData.label.forEach(function(label) {
      if (label) {
        numFragments++;
      }
    });

    return numFragments;
  };

  $scope.getFragmentedBonds = function() {

    var numBonds = $scope.set.peptide.sequence.length - 1;
    var bondArray = new Array(numBonds).fill(0);

    $scope.set.plotData.label.forEach(function(label) {
      var text = label.charAt(0);
      var location = label.slice(1);

      if (text == "a" || text == "b" || text == "c" || text == "C") {
        bondArray[location - 1] = 1;
      } else  if (text == "x" || text == "y" || text == "z" || text == "Z") {
        bondArray[-(location - numBonds)] = 1;
      }
    });

    var uniqueBondsBroken = bondArray.reduce(function(a, b) { return a + b; }, 0);
    return uniqueBondsBroken;
  };

  $scope.formatModsForDownload = function() {
    var returnString = "\"";
    $scope.peptide.mods.forEach(function(mod) {
      var modString = "<";
      var index = mod.index;

      if (index == 0) {
        index = "N-terminus";
      } else if (index == $scope.set.peptide.sequence.length) {
        index = "C-terminus";
      }

      modString += mod.name + ";" + (index) + ";" + d3.format("0.4f")($scope.annotatedResults.modifications[mod.index + 1].deltaMass) + ">";
      returnString += modString;
    });

    if (returnString != "\"") {
      returnString += "\"";
    } else {
      return "";
    }

    return returnString;
  };

  $scope.formatReturnedModsForDownload = function(mods) {
    var returnString = "";

    mods.forEach(function(mod) {
      var modString = "<";
      var index = mod.site + 1;
      var name = "";

      $scope.peptide.mods.forEach(function(selectedMod) {
        if (mod.site == selectedMod.index && mod.deltaElement == selectedMod.elementChange) {
          name = selectedMod.name;
        }
      });

      if (index == 0) {
        index = "N-terminus";
      } else if (index == $scope.set.peptide.sequence.length + 1) {
        index = "C-terminus";
      }

      modString += name + ";" + (index) + ";" + d3.format("0.4f")(mod.deltaMass) + ">";
      returnString += modString;
    });

    return returnString;
  };

  $scope.getPercentTicExplained = function() {
    var count = $scope.set.plotData.label.length;
    var fragmentIntensity = 0;

    for (var i = 0; i < count; i++) {
      if ($scope.set.plotData.label[i]) {
        fragmentIntensity += $scope.set.plotData.y[i];
      }
    }

    return d3.format("0.2%")(fragmentIntensity / $scope.set.plotData.TIC);
  };

  $scope.formatMatchedFragmentRow = function() {
    var fragmentRows = [];
    var count = $scope.set.plotData.x.length;
    for (var i = 0; i < count; i++) {
      var row = "";

      var label = $scope.set.plotData.label[i];

      if (label) {
        var type = $scope.getFragmentType(label); 
        var number = $scope.getFragmentNumber(label);
        var mods = $scope.getFragmentModifications(type, number);
        mods = $scope.formatReturnedModsForDownload(mods);
        var neutralLoss = $scope.set.plotData.neutralLosses[i];
        var mz = $scope.set.plotData.x[i];
        var charge = "";
        if ($scope.set.settings.ionizationMode == "+") {
          charge = $scope.set.plotData.labelCharge[i];
        } else {
          charge = "-" + $scope.set.plotData.labelCharge[i];
        }
        var intensity = $scope.set.plotData.y[i];
        var theoMz =  $scope.set.plotData.theoMz[i];
        var error = $scope.set.plotData.massError[i];
        var percentBasePeak = $scope.set.plotData.percentBasePeak[i];
        var percentTIC = intensity / $scope.set.plotData.TIC;

        row += type + "," + number + "," + mods + ", " + neutralLoss + ", " + charge + "," + intensity + "," + d3.format("0.4f")(mz) + "," + d3.format("0.4f")(theoMz) + "," + 
          d3.format("0.4f")(error) + "," + d3.format("0.2f")(percentBasePeak) + "%," + d3.format("0.2%")(percentTIC);
        fragmentRows.push(row);
      }
    };

    return fragmentRows;
  };

  $scope.getFragmentType = function(label) {
    var char = label.charAt(0);

    if (char == "[") {
      return label.slice(1, -1);
    } else if (char == "C") {
      return "[c-1]";
    } else if (char == "Z") {
      return "[z+1]";
    } else {
      return char;
    }
  };

  $scope.getFragmentNumber = function(label) {
    var char = label.charAt(0);

    if (char == "[") {
      return "";
    } else {
      return parseInt(label.slice(1));
    }
  }

  $scope.getFragmentModifications = function(type, number) {
    var returnArray = [];
    var possibleMods = [];
    if (type == "a" || type == "b" || type == "c" || type == "[c-1]") {
      possibleMods = $scope.annotatedResults.modifications.slice(0, number + 1);
    } else if (type == "x" || type == "y" || type == "z" || type == "[z+1]") {
      possibleMods =  $scope.annotatedResults.modifications.slice(-number - 1);
    }

    possibleMods.forEach(function(mod) {
      if (mod.deltaMass) {
        returnArray.push(mod); 
      }
    });

    return returnArray;
  };
 
  $scope.AnnotateAllData = function() {
    $scope.isProcessing = true;
    var url = "";

    if ($scope.ids[0].charge < 0) {
      url = "support/php/NegativeModeAnnotateEntireFile.php";
    } else {
      url = "support/php/AnnotateEntireFile.php";
    }
    

    var data = {
      timeStamp: $localStorage.timeStampIdentifier,
      "fragmentTypes": $scope.checkModel,
      "toleranceType": $scope.cutoffs.toleranceType,
      "tolerance": $scope.cutoffs.tolerance,
      "matchingType": $scope.cutoffs.matchingType,
      "cutoff": $scope.cutoffs.matchingCutoff
    };

    $http.post(url, data)
      .then( function(response) {
        if (response.data.hasOwnProperty("error")) {
          alert(response.data.error);
          $scope.isProcessing = false;
        } else {
          $log.log(response);

          var a = document.createElement('a');
          a.href = response.data.downloadPath;
          a.download = response.data.fileName;
          document.body.appendChild(a);
          a.click();
          a.remove();

          $scope.isProcessing = false;

          url = "support/php/ClearDownloadFolder.php"
          $http.post(url, data)
            .then( function(response) {
              $log.log(response);
          });

        }
    });
  };
}]);
