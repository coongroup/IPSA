<?php
require("config.php");

class ChemistryConstants {
	public static $Proton = 1.007276466879;
	public static $H = 1.00782503223;
	public static $h = 2.01410177812;
	public static $C = 12.0;
	public static $c = 13.00335483507;
	public static $N = 14.00307400443;
	public static $n = 15.00010889888;
	public static $O = 15.99491461957;
	public static $o = 17.99915961286;
	public static $Na = 22.9897692820;
	public static $P = 30.97376199842;
	public static $S = 31.9720711744;
}

class AminoAcids {
	public static $A = 71.037114;
	public static $C = 103.00919;
	public static $D = 115.02694;
	public static $E = 129.04259;
	public static $F = 147.06841;
	public static $G = 57.021464;
	public static $H = 137.05891;
	public static $I = 113.08406;
	public static $K = 128.09496;
	public static $L = 113.08406;
	public static $M = 131.04048;
	public static $N = 114.04293;
	public static $P = 97.052764;
	public static $Q = 128.05858;
	public static $R = 156.10111;
	public static $S = 87.032029;
	public static $T = 101.04768;
	public static $V = 99.068414;
	public static $W = 186.07931;
	public static $Y = 163.06333;
}

class AminoAcid {
	public $mass;
	public $modification;
	public $name;
	
	function AminoAcid($name, $modification) {
		$this->name = $name;
		$this->modification = $modification;
		$this->mass = AminoAcids::${$this->name} + $this->modification->deltaMass;
	}
}

class Peptide {
	public $aminoAcids;
	public $precursorMz;
	public $precursorMass;
	public $precursorCharge;
	public $charge;
	public $fragments;
	public $fragTypes;
	public $modifications;
	public $peaks;
	public $basePeak;
	public $sequence;
	public $tolerance;
	public $matchType;
	public $cutoff;
	public $isPPM;
	public $checkVar;
	public $tic;
	
	public function Peptide($sequence, $precursorCharge, $charge, $fragTypes, $tolerance, $toleranceType, $matchType, $cutoff, $basePeak, $tic) {
		$this->sequence = strtoupper($sequence);
		$this->precursorCharge = $precursorCharge;
		$this->charge = $charge;
		$this->fragTypes = $fragTypes;
		$this->peaks = array();
		$this->fragments = array();
		$this->tolerance = $tolerance;
		$this->isPPM = $toleranceType === "ppm";
		$this->matchType = $matchType;
		$this->cutoff = $cutoff;
		$this->basePeak = $basePeak;
		$this->tic = $tic;
	}
	
	function AddPeaks($data) {
		//echo json_encode($data);
		foreach ($data as $feature) {
			array_push($this->peaks, new Peak($feature, $this->basePeak));
		}
	}
	
	function AddMods($mods) {
		$this->modifications = array();
		// from n-term (index -1) to c term (index sequence.length)
		for ($i = -1; $i <= strlen($this->sequence); $i++) {
			$modification = null;
			for ($j = 0; $j < count($mods); $j++) {
				
				if ($mods[$j]->index == $i) {
					$modification = $mods[$j];
					break;
				}
			}
			array_push($this->modifications, new Modification($modification, $i));
		}
		$this->AddAminoAcids();
	}
	
	function AddAminoAcids() {
		$this->aminoAcids = array();
		for ($i = 0; $i < strlen($this->sequence); $i++) {
			array_push($this->aminoAcids, new AminoAcid($this->sequence[$i], $this->modifications[$i+1]));
		}
	}
	
	function CalculatePrecursorMass() {
		$mass = 0;
		foreach ($this->aminoAcids as $aa) {
			$mass += $aa->mass;
		}
		
		// n and c term mods
		$mass += $this->modifications[0]->deltaMass + end($this->modifications)->deltaMass;
		$mass += 2 * ChemistryConstants::$H + ChemistryConstants::$O;
		
		$this->precursorMz = ($mass - $this->precursorCharge * ChemistryConstants::$Proton) / $this->precursorCharge;
		
		return $mass;
	}
	
	function CalculateFragmentMZs() {
		$length = count($this->aminoAcids);
		
		if ($this->fragTypes->a->selected) {
			for ($i = 1; $i < $length; $i++) {
				$subPeptide = array_slice($this->aminoAcids, 0, $i);
				$deltaMass = $this->modifications[0]->deltaMass - ChemistryConstants::$C - ChemistryConstants::$O + ChemistryConstants::$Proton;
				$fragmentMZs = $this->CreateFragment($subPeptide, "a", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "a", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "a", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "a", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "a", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->b->selected) {
			for ($i = 1; $i < $length; $i++) {
				$subPeptide = array_slice($this->aminoAcids, 0, $i);
				$deltaMass = $this->modifications[0]->deltaMass;
				$fragmentMZs = $this->CreateFragment($subPeptide, "b", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "b", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "b", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "b", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
	
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "b", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->c->selected) {
			for ($i = 1; $i < $length; $i++) {
				$subPeptide = array_slice($this->aminoAcids, 0, $i);
				$deltaMass = $this->modifications[0]->deltaMass + 3 * ChemistryConstants::$H + ChemistryConstants::$N;
				$fragmentMZs = $this->CreateFragment($subPeptide, "c", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "c", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "c", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "c", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "c", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->C->selected) {
			for ($i = 1; $i < $length; $i++) {
				$subPeptide = array_slice($this->aminoAcids, 0, $i);
				$deltaMass = $this->modifications[0]->deltaMass + 2 * ChemistryConstants::$H + ChemistryConstants::$N;
				$fragmentMZs = $this->CreateFragment($subPeptide, "C", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "C", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "C", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "C", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "C", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		
		if ($this->fragTypes->x->selected) {
			for ($i = -1; $i > -$length; $i--) {
				$subPeptide= array_slice($this->aminoAcids, $i);
				$deltaMass = end($this->modifications)->deltaMass + 2 * ChemistryConstants::$O + ChemistryConstants::$C;
				$fragmentMZs = $this->CreateFragment($subPeptide, "x", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "x", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "x", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "x", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "x", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->y->selected) {
			for ($i = -1; $i > -$length; $i--) {
				$subPeptide= array_slice($this->aminoAcids, $i);
				$deltaMass = end($this->modifications)->deltaMass+ 2 * ChemistryConstants::$H + ChemistryConstants::$O;
				$fragmentMZs = $this->CreateFragment($subPeptide, "y", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "y", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "y", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "y", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "y", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->z->selected) {
			for ($i = -1; $i > -$length; $i--) {
				$subPeptide= array_slice($this->aminoAcids, $i);
				$deltaMass = end($this->modifications)->deltaMass+ ChemistryConstants::$O - ChemistryConstants::$N;
				$fragmentMZs = $this->CreateFragment($subPeptide, "z", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "z", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "z", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "z", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "z", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
		if ($this->fragTypes->Z->selected) {
			for ($i = -1; $i > -$length; $i--) {
				$subPeptide= array_slice($this->aminoAcids, $i);
				$deltaMass = end($this->modifications)->deltaMass + ChemistryConstants::$O - ChemistryConstants::$N - ChemistryConstants::$H;
				$fragmentMZs = $this->CreateFragment($subPeptide, "Z", $deltaMass);
				foreach ($fragmentMZs as $fragment) {
					array_push($this->fragments, $fragment);
				}
				
				if ($this->fragTypes->H2O->selected && preg_match("/[STED]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$O + ChemistryConstants::$H * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "Z", $tempMass, "-H2O");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->NH3->selected && preg_match("/[RKQN]/", $this->GetSubPeptideSequence($subPeptide))) {
					$tempMass = $deltaMass;
					$tempMass-= ChemistryConstants::$N + ChemistryConstants::$H * 3;
					$fragmentMZs = $this->CreateFragment($subPeptide, "Z", $tempMass, "-NH3");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}
				
				if ($this->fragTypes->CO2->selected) {
					$tempMass = $deltaMass;
					$tempMass -= ChemistryConstants::$C + ChemistryConstants::$O * 2;
					$fragmentMZs = $this->CreateFragment($subPeptide, "Z", $tempMass, "-CO2");
					foreach ($fragmentMZs as $fragment) {
						array_push($this->fragments, $fragment);
					}
				}

				if ($this->fragTypes->HPO3->selected) {
					preg_match("/[STY]/", $this->GetSubPeptideSequence($subPeptide), $outArray, PREG_OFFSET_CAPTURE);
					
					if ($outArray) {
						foreach ($outArray as $match) {
							for ($j = 0; $j < count($this->modifications); $j++) {
								$mod = $this->modifications[$j];
								if ($mod->site == $match[1] && $mod->deltaElement == "H1 O3 P1") {
									$this->checkVar = "We're in";
									$tempMass = $deltaMass;
									$tempMass -= ChemistryConstants::$P + ChemistryConstants::$O * 3 + ChemistryConstants::$H;
									$fragmentMZs = $this->CreateFragment($subPeptide, "Z", $tempMass, "-HPO3");
									foreach ($fragmentMZs as $fragment) {
										array_push($this->fragments, $fragment);
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	private function CreateFragment($subPeptide, $type, $deltaMass, $neutralLoss = "") {
		$mass = $deltaMass;
		$length = count($subPeptide);
		
		for ($i = 0; $i < $length; $i++) {
			$aminoAcid = $subPeptide[$i];
			$mass += $aminoAcid->mass;
		}
		$returnArray = array();
		
		for ($i = $this->charge; $i > 0; $i--) {
			array_push($returnArray, new PeptideFragment($type, $neutralLoss, $length, ($mass - ChemistryConstants::$Proton * $i)/$i, $subPeptide, $i));
		}
		
		return $returnArray;
	}
	
	private function GetSubPeptideSequence($subPeptide) {
		$sequence = "";
		foreach ($subPeptide as $aa) {
			$sequence = $sequence . $aa->name; 
		}
		return $sequence;
	}
	
	function MatchFragments() {
		// within provided tolerance, (10 ppm), match theoretical fragments to peaks
		foreach ($this->fragments as $fragment) {
			$tolerance = new Tolerance($fragment->mz, $this->tolerance, $this->isPPM);
			
			foreach ($this->peaks as $peak) {
				if ($tolerance->Contains($peak)) {
					
					if ($this->matchType == "Intensity" && $peak->intensity >= $this->cutoff) {
						$newMatchedFeature = new MatchedFeature($fragment, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					} else if ($this->matchType == "% Base Peak" && $peak->percentBasePeak >= $this->cutoff) {
						$newMatchedFeature = new MatchedFeature($fragment, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					} else if ($this->matchType == "S/N" && $peak->sn >= $this->cutoff) {
						$newMatchedFeature = new MatchedFeature($fragment, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					}
				}
			}
		}
		
		// match precursor to a peak (and charge reduced precursors?)
		for ($i = $this->precursorCharge; $i > 0; $i--) {
			$precursor = new Precursor($this->precursorMass, $i, $this->precursorCharge);
			$tolerance = new Tolerance($precursor->mz, $this->tolerance, $this->isPPM);

			foreach ($this->peaks as $peak) {
				if ($tolerance->Contains($peak)) {
					$newMatchedFeature = new MatchedFeature($precursor, $peak->mz, $this->isPPM);
					array_push($peak->matchedFeatures, $newMatchedFeature);
				}
			}
		}
		
		// look for precursor neutral losses 
		for ($i = $this->precursorCharge; $i > 0; $i--) {
			if ($this->fragTypes->NH3->selected) {
				$lossMass = $this->precursorMass - ChemistryConstants::$N - ChemistryConstants::$H * 3;
				$precursor = new Precursor($lossMass, $i, $this->precursorCharge, "NH3");
				$tolerance = new Tolerance($precursor->mz, $this->tolerance, $this->isPPM);

				foreach ($this->peaks as $peak) {
					if ($tolerance->Contains($peak)) {
						$newMatchedFeature = new MatchedFeature($precursor, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					}
				}
			} else if ($this->fragTypes->H2O->selected) {
				$lossMass = $this->precursorMass - ChemistryConstants::$O - ChemistryConstants::$H * 2;
				$precursor = new Precursor($lossMass, $i, $this->precursorCharge, "H2O");
				$tolerance = new Tolerance($precursor->mz, $this->tolerance, $this->isPPM);

				foreach ($this->peaks as $peak) {
					if ($tolerance->Contains($peak)) {
						$newMatchedFeature = new MatchedFeature($precursor, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					}
				}
			} else if ($this->fragTypes->CO2->selected) {
				$lossMass = $this->precursorMass - ChemistryConstants::$C - ChemistryConstants::$O * 2;
				$precursor = new Precursor($lossMass, $i, $this->precursorCharge, "CO2");
				$tolerance = new Tolerance($precursor->mz, $this->tolerance, $this->isPPM);

				foreach ($this->peaks as $peak) {
					if ($tolerance->Contains($peak)) {
						$newMatchedFeature = new MatchedFeature($precursor, $peak->mz, $this->isPPM);
						array_push($peak->matchedFeatures, $newMatchedFeature);
					}
				}
			}
		}
		
		foreach ($this->peaks as $peak) {
			if ($peak->matchedFeatures !== NULL) {
				usort($peak->matchedFeatures, function($a, $b)
				{
					return abs($a->massError) - abs($b->massError);
				});
			}
		}
	}
} 

function FormatPeptideModsForDownload($modList) {
	$returnString = "";
	
	foreach($modList as $mod) {
		if ($mod->deltaMass) {
			$returnString = $returnString . "<" . $mod->name . ";" . $mod->site . ";" . $mod->deltaMass . ">";
		}
	}
	
	return $returnString;
}

function FormatFragmentModsForDownload($subPeptide) {
	$returnString = "";
	
	foreach($modList as $mod) {
		if ($mod->deltaMass) {
			$returnString = $returnString . "<" . $mod->name . ";" . $mod->site . ";" . $mod->deltaMass . ">";
		}
	}
	
	return $returnString;
}

class PeptideFragment {
	public $charge;
	public $mz;
	public $number;
	public $sequence;
	public $subPeptide;
	public $type;
	public $neutralLoss;
	
	function PeptideFragment($type, $neutralLoss, $number, $mz, $subPeptide, $charge) {
		$this->charge = $charge;
		$this->mz = $mz;
		$this->number = $number;
		$this->sequence = $this->ReturnSequence($subPeptide);
		$this->subPeptide = $subPeptide;
		$this->type = $type;
		
		if ($neutralLoss != null) {
			$this->neutralLoss = $neutralLoss;
		} else {
			$neutralLoss = "";
		}
	}
	
	function NeutralLoss($loss) {
		$this->neutralLoss = $loss;
	}
	
	private function ReturnSequence($subPeptide) {
		$returnString = "";
		foreach ($subPeptide as $aminoAcid) {
			$returnString = $returnString . $aminoAcid->name;
		}
		return $returnString;
	}
}

class Precursor {
	public $charge;
	public $mz;
	public $number;
	public $type;
	public $isPrecursor = true;
	public $neutralLoss = "";
	
	function Precursor($mass, $charge, $originalCharge, $neutralLoss = "") {
		$this->charge = $charge;
		$this->mz = ($mass - ChemistryConstants::$Proton * $originalCharge) / $charge;
		// signals precursor
		if ($originalCharge == 1) {
			$this->number = "-" . "H";
		} else {
			$this->number = "-" . "$originalCharge" . "H";
		}
		$this->type = "M";

		if ($neutralLoss) {
			$this->neutralLoss = "-" . $neutralLoss;
		}
	}
}

class Peak {
	public $mz;
	public $intensity;
	public $percentBasePeak;
	public $percentTic;
	public $sn;
	public $matchedFeatures;
	
	function Peak($feature, $basePeak) {
		$this->mz = $feature->mZ;
		$this->intensity = $feature->intensity;
		$this->percentBasePeak = 100 * $feature->intensity / $basePeak->intensity;
		$this->percentTic = 100 * $feature->intensity / Feature::$tic;
		if (isset($feature->sN)) {
			$this->sn = $feature->sN;
		} else {
			$this->sn = null;
		}
		
		$this->matchedFeatures = array();
	}
}

class Tolerance {
	public $maxMz;
	public $centerMz;
	public $minMz;
	public $tolerance; 
	public $isPPM;
	
	public function Tolerance($targetMz, $tolerance, $isPPM) {
		$this->centerMz = $targetMz;
		$this->tolerance = $tolerance;
		$this->isPPM = $isPPM;
		
		if ($isPPM) {
			$halfRange = ($tolerance * $this->centerMz) / 1000000;
			$this->maxMz = $this->centerMz + $halfRange;
			$this->minMz = $this->centerMz - $halfRange;
		} else {
			$this->maxMz = $this->centerMz + $tolerance;
			$this->minMz = $this->centerMz - $tolerance;
		}
	}
	
	public function Contains($peak) {
		if ($this->minMz < $peak->mz && $this->maxMz > $peak->mz) {
			return true;
		} else {
			return false;
		}
	}
}

class Modification {
	// zero indexed. -1 = nterm, sequence_length = c term
	public $name;
	public $site;
	public $deltaElement;
	public $deltaMass;
	
	function Modification($mod, $site) {
		if ($mod === null) {
			$this->name = "";
			$this->site = $site;
			$this->deltaMass = 0;
		}
		else {
			$this->name = $mod->name;
			$this->site = $mod->index;
			if (isset($mod->elementChange)) {
				$this->deltaElement = $mod->elementChange;
				$this->deltaMass = $this->CalculateDeltaMass($this->deltaElement);
			} else {
				$this->deltaMass = $mod->deltaMass;
			}
		}
	}
	
	private function CalculateDeltaMass($elementChange) {
		// deltaElement is a string seperated by spaces
		$deltaMass = 0;
		$elements = explode(" ", $elementChange);
		foreach ($elements as $element) {
			$arr = preg_split("/(?<=[a-z])(?=[0-9\-]+)/i", $element);
			$deltaMass += ChemistryConstants::${$arr[0]} * $arr[1];
		}
		return $deltaMass;
	}
}

class MatchedFeature {
	public $feature;
	public $massError;
	
	function MatchedFeature($matchedFeature, $matchedPeakMz, $isPPM) {
		$this->feature = $matchedFeature;
		
		if ($isPPM) {
			$this->massError = (($matchedPeakMz - $matchedFeature->mz) / $matchedFeature->mz) * 1000000;
		} else {
			$this->massError = $matchedPeakMz - $matchedFeature->mz;
		}
	}
}

function BasePeak($data) {
	$returnPeak = $data[0];
	
	foreach ($data as $peak) {
		if ($returnPeak->intensity < $peak->intensity) {
			$returnPeak = $peak;
		}
	}
	return $returnPeak;
}

function TIC($data) {
	$returnTIC = 0;
	
	foreach ($data as $peak) {
		$returnTIC += $peak->intensity;
	}
	return $returnTIC;
}

class ID {
	public $sequence;
	public $scanNumber;
	public $charge;
	public $mods;
	
	public function ID($sequence, $scanNumber, $charge, $mods) {
		$this->sequence = $sequence;
		$this->scanNumber = $scanNumber;
		$this->charge = abs($charge);
		$this->mods = $mods;
	}
}

class Mod {
	public $name;
	public $deltaMass;
	
	public function Mod($name, $mass) {
		$this->name = $name;
		$this->deltaMass = $mass;
	}
}

class TemporaryModificationObject {
	public $deltaMass;
	public $index;
	public $name;
	
	function TemporaryModificationObject($name, $deltaMass, $index) {
		$this->name = $name;
		$this->deltaMass = $deltaMass;
		$this->index = $index;
	}
}

function LinkModsToPeptide($databaseMods, $id) {
	$returnArray = array();
	$peptideMods = $id->mods;
	
	foreach ($peptideMods as $peptideMod) {
		$splitMod = explode(":", $peptideMod);
		foreach($databaseMods as $databaseMod) {
			if ($databaseMod->name == $splitMod[0]) {
				$site = "";
				
				if ($splitMod[1]) {
					if (preg_match("/^[Nn]/", $splitMod[1])) {
						$site = -1;
					} else if (preg_match("/^[0-9]/", $splitMod[1])) {
						$site = doubleval($splitMod[1]) - 1;
					} else if (preg_match("/^[Cc]/", $splitMod[1])) {
						$site = strlen($id->sequence);
					} else {
						echo (json_encode(array("error" => "Invalid modification site detected for scan number " + $id->scanNumber + "! Please double check the modifications are correctly formated. Permitted modification indexes are N, C, or amino acid location (1-peptide length)")));
					}
				}
				array_push($returnArray, new TemporaryModificationObject($databaseMod->name, $databaseMod->deltaMass, $site));
			}
		}
	}
	
	return $returnArray;
}

function CalculateBondsBroken($fragmentList, $length) {
	$nTerminal = array_fill(0, $length - 1, 0);
	$cTerminal = array_fill(0, $length - 1, 0);
	$returnSum = 0;
	
	foreach ($fragmentList as $fragment) {
		$type = $fragment->feature->type;
		if ($type == "a" || $type == "b" || $type == "c" || $type == "C") {
			$nTerminal[$fragment->feature->number - 1] = 1;
		} else if ($type == "x" || $type == "y" || $type == "z" || $type == "Z") {
			$cTerminal[$fragment->feature->number - 1] = 1;
		}
	}
	$cTerminal = array_reverse($cTerminal);
	
	for ($i = 0; $i < $length - 1; $i++) {
		if ($nTerminal[$i] || $cTerminal[$i]) {
			$returnSum++;
		}
	}
	
	return $returnSum;
}

class Feature {
	public $mZ = -1;
	public $intensity = -1;
	public static $tic = 0;
	
	function Feature($mz, $int) {
		$this->mZ = $mz;
		$this->intensity = $int;
		Feature::$tic += $int;
	}
}

function sortByScanNumber($a, $b) {
	if($a->scanNumber== $b->scanNumber)
	{ 
		return 0; 
	} else {
		return ($a->scanNumber< $b->scanNumber) ? -1 : 1;
	}
}

function sortByMassError($a, $b) {
	$A = abs($a->massError);
	$B = abs($b->massError);
	if($A == $B) { 
		return 0; 
	} else {
		return ($A < $B) ? -1 : 1;
	}
}

function delete_files($target) {
	if(is_dir($target)) {
		$files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned

		foreach( $files as $file ) {
			delete_files( $file );
		}
		rmdir( $target );

	} elseif(is_file($target)) {
		unlink( $target );  
	}
}

$IDs = array();
$mods = array();


// decode data from post
$data = json_decode(file_get_contents("php://input"));
$timeStamp = $data->timeStamp;
$frags = $data->fragmentTypes;

if (!$data->fragmentTypes->C->selected && !$data->fragmentTypes->a->selected && !$data->fragmentTypes->b->selected && !$data->fragmentTypes->c->selected && !$data->fragmentTypes->Z->selected && !$data->fragmentTypes->x->selected && !$data->fragmentTypes->y->selected && !$data->fragmentTypes->z->selected) {
	echo json_encode(array("error" => "Please select a peptide fragmentation pattern!"));
} else {	
	// Retrieve IDs from database
	$stmt = $pdo->prepare('SELECT sequence, scan_number, charge, mods FROM identifications WHERE timestamp=:timestamp ORDER BY sequence');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded identifications.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$IDs[] = new ID($result[0], $result[1], $result[2], explode(";", $result[3]));
		}
	}
	
	// Retrieve mods from database
	$stmt = $pdo->prepare('SELECT name, mass FROM mods WHERE timestamp=:timestamp');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded modifications.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$mods[] = new Mod($result[0], doubleval($result[1]));
		}
	}
	

	usort($IDs, 'sortByScanNumber');
	
	// ready to process files and download
	// create a file pointer connected to the output stream
	$fullSummaryPath = $downloadPath . $timeStamp . "_Summary.csv";
	$fullFragmentPath = $downloadPath . $timeStamp . "_Fragments.csv";
	$summaryOutput = fopen($fullSummaryPath, 'w');
	$fragmentOutput = fopen($fullFragmentPath, 'w');
	
	// output the column headings
	fputcsv($summaryOutput, array('Scan Number','Sequence','Theoretical Mz','Charge','Modifications <Name;Index;Mass Change>','# Matched Fragments','# Bonds Broken','% TIC Explained','Peak Count'));
	fputcsv($fragmentOutput, array('Scan Number','Fragment Type','Fragment Bond Number','Neutral Loss','Fragment Charge','Intensity','Experimental Mz','Theoretical Mz','Mass Error (ppm)','% Base Peak','% TIC'));
	
	for ($i = 0; $i < count($IDs); $i++) {
		$id = $IDs[$i];
		Feature::$tic = 0;
		
		$MZs = array();
		$intensities = array();
		$features = array();
		
		// get spectral data
		$stmt = $pdo->prepare('SELECT mz, intensity FROM spectra WHERE timestamp=:timestamp AND scan_number=:scanNumber');
		$success = $stmt->execute(['timestamp' => $timeStamp, 'scanNumber' => $id->scanNumber]);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "Could not retrieve previously uploaded modifications.")));
			die;
		} else {
			while($result = $stmt->fetch())
			{
				$MZs = explode(";", $result[0]);
				$intensities = explode(";", $result[1]);
				
				for($j = 0; $j < count($MZs); $j++) {
					array_push($features, new Feature(doubleval($MZs[$j]), doubleval($intensities[$j])));
				}
			}
		}
		
		// calculate base peak and TIC
		$basePeak = BasePeak($features);
		
		$tic = TIC($features);
		$peptide = new Peptide($id->sequence, $id->charge, $id->charge, $data->fragmentTypes, $data->tolerance, $data->toleranceType, $data->matchingType, $data->cutoff, $basePeak, $tic);
		
		// link peptide mods to the modification files
		$peptide->AddMods(LinkModsToPeptide($mods, $id));
		$peptide->AddPeaks($features, $basePeak);
		$peptide->precursorMass = $peptide->CalculatePrecursorMass();
		//generate all theoretical peptide fragments
		$peptide->CalculateFragmentMZs();
		// match these fragments to the submitted data
		$peptide->MatchFragments();
		
		$percentTic = 0;
		$matchedFeatureList = array();
		$experimentalFeatureList = array();
		// output the column headings
		foreach ($peptide->peaks as $peak) {
			if (count($peak->matchedFeatures) > 0) {
				usort($peak->matchedFeatures, 'sortByMassError');
				
				$percentTic += $peak->percentTic;
				array_push($matchedFeatureList, $peak->matchedFeatures[0]);
				array_push($experimentalFeatureList, $peak);
			}
		}

		fputcsv($summaryOutput, array($id->scanNumber, $peptide->sequence, $peptide->precursorMz, " -" . $peptide->precursorCharge, FormatPeptideModsForDownload($peptide->modifications), count($matchedFeatureList), CalculateBondsBroken($matchedFeatureList, strlen($peptide->sequence)) , $percentTic . "%", count($peptide->peaks)));
		
		for($j = 0; $j < count($matchedFeatureList); $j++) {
			$feature = $matchedFeatureList[$j]->feature;
			$peak = $experimentalFeatureList[$j];
			fputcsv($fragmentOutput, array($id->scanNumber, $feature->type, $feature->number, " " . $feature->neutralLoss, " -" . $feature->charge, $peak->intensity, $peak->mz, $feature->mz, $matchedFeatureList[$j]->massError, $peak->percentBasePeak . "%", $peak->percentTic . "%"));
		}
	}
	// close files
	fclose($summaryOutput);
	fclose($fragmentOutput);

	// create folder to zip
	$resultPath = $downloadPath . $timeStamp . "_Results/";

	delete_files($resultPath);

	mkdir($resultPath);
	//transfer output files to folder
	rename($fullSummaryPath, $resultPath . $timeStamp . "_Summary.csv");
	rename($fullFragmentPath, $resultPath . $timeStamp . "_Fragments.csv");
	
	$zip_file = $downloadPath . $timeStamp . "_Results.zip";

	// Get real path for our folder
	$rootPath = realpath($resultPath);

	// Initialize archive object
	$zip = new ZipArchive();
	$zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	// Create recursive directory iterator
	/** @var SplFileInfo[] $files */
	$files = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator($rootPath),
	    RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $name => $file)
	{
	    // Skip directories (they would be added automatically)
	    if (!$file->isDir())
	    {
	        // Get real and relative path for current file
	        $filePath = $file->getRealPath();
	        $relativePath = substr($filePath, strlen($rootPath) + 1);

	        // Add current file to archive
	        $zip->addFile($filePath, $relativePath);
	    }
	}

	// Zip archive will be created only after closing object
	$zip->close();

	// remove uncompressed files and folder path
	delete_files($resultPath);

	// provide link back to client for zip download
	echo (json_encode(Array("IDs" => $IDs, "downloadPath" => $relativeZipPath . $timeStamp . "_Results.zip", "fileName" => $timeStamp . "_Results.zip")));
}
?>