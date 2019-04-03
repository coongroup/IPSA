// This javascript file populates IPSA's rendered spectrum with randomly generated data using the 
// peptide sequence TESTPEPTIDE in a +2 charge state.


// link IPSA to this custom javascript file
angular.module("IPSA.directive");

var myApp = angular.module('myApp', ["IPSA.directive"]);

/* master controller to carry similar functionality to $rootScope */
myApp.controller('plotCtrl', function($scope, $log, $http) {
  // $scope.myData is a JavaScript object which will pass all required data over to IPSA to parse.
  
  // create a function to fill in modifications
  var populateMods = function(pepSequence) {
    var returnArray = [];
    for (var i = 0; i < pepSequence.length + 2; i++) {
      returnArray.push({
        site: i - 1,
        deltaElement: 0,
        deltaMass: 0
      });
    }
    return returnArray;
  }

  $scope.myData = {
  	// $scope.myData.plotData contains arrays which correspond to your spectral data and annotations
    plotData: 
    {
      x: [ ],									// (float) spectral peak m/z values
      y: [ ],									// (float) spectral peak intensities (absolute, relative, ect.)
      color: [ ],							// (string) spectral peak colors in HEX format
      label: [ ],							// (string) text to include over an annotated spectral peak
      labelCharge: [ ],				// (int) the charge states of annotated fragments
      neutralLosses: [ ],			// (string) neutral losses of an annotated spectral peak (if applicable)
      barwidth: [ ],					// (int) width of rendered spectral peaks (1 is suggested)
      massError: [ ], 				// (string) mass error of annotated spectral peak. 
      theoMz: [ ],						// (float) theoreatical m/z of an annotated spectral peak.
      percentBasePeak: [ ],		// (float) relative intensity of a spectral peak.
      TIC: 0									// (float) total ion current for this spectrum.
    },
    // $scope.myData.peptide contains data pertaining to your peptide sequence, precursor mz, precurosor charge 
    peptide: 
    {
      sequence: "TESTPEPTIDE", 	// (string) peptide sequence
      precursorMz: 609.77229,		// (float) precursor mz
      precursorCharge: 2,				// (int) precursor charge
      mods: populateMods("TESTPEPTIDE") // (string) modifications for each amino acid
    },
    // $scope.myData.settings contains values which handles several annotation settings
    settings: 
    {
      toleranceThreshold: 0,	// 
      toleranceType: "",			// (string) "ppm" or "Da"
      ionizationMode: ""			// (string) "+" or "-"
    }
  };

  // generate random spectral data for the spectrum render. 150 peaks. m/zs from 0 -> 2000. relative intensities from 0 -> 100
  $scope.randomize = function() {
    $scope.myData.plotData.x = [ ];
    $scope.myData.plotData.y = [ ];
    $scope.myData.plotData.color = [ ];
    $scope.myData.plotData.label = [ ];
    $scope.myData.plotData.labelCharge = [ ];
    $scope.myData.plotData.neutralLosses = [ ];
    $scope.myData.plotData.barwidth = [ ];
    $scope.myData.plotData.massError = [ ];
    $scope.myData.plotData.theoMz = [ ];
    $scope.myData.plotData.percentBasePeak = [ ];
    $scope.myData.plotData.TIC = 0;

    $log.log("randomizing");
    _.times(150, function(n) {
      var x = _.random(0, 2000);
      var y = _.random(0, 100);
      if (y < 1) {
        y = 1;
      }
      $scope.myData.plotData.TIC += y;
      $scope.myData.plotData.y.push(y);
      $scope.myData.plotData.x.push(x);
      $scope.myData.plotData.color.push("#A6A6A6");
      $scope.myData.plotData.label.push("");
      $scope.myData.plotData.labelCharge.push(0);
      $scope.myData.plotData.neutralLosses.push("");
      $scope.myData.plotData.barwidth.push(1);
      $scope.myData.plotData.massError.push("");
      $scope.myData.plotData.theoMz.push(0);
      $scope.myData.plotData.percentBasePeak.push(100 * y/d3.max($scope.myData.plotData.y));
    });

    $scope.myData.plotData.x.sort(function(a, b){return a - b});
    $scope.myData.settings.toleranceType = "ppm";
    $scope.myData.settings.toleranceThreshold = 10;
    $scope.myData.settings.ionizationMode = "+";
    $log.log($scope.myData);
  };
});