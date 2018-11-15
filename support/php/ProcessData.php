<?php
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
	public $annotationTime;
	public $checkVar;
	

	public function Peptide($sequence, $precursorCharge, $charge, $fragTypes, $tolerance, $toleranceType, $matchType, $cutoff, $basePeak) {
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
		
		$this->precursorMz = ($mass + $this->precursorCharge * ChemistryConstants::$Proton) / $this->precursorCharge;
		
		return $mass;
	}
	
	function CalculateFragmentMZs() {
		$length = count($this->aminoAcids);
		
		if ($this->fragTypes->a->selected) {
			for ($i = 1; $i < $length; $i++) {
				$subPeptide = array_slice($this->aminoAcids, 0, $i);
				$deltaMass = $this->modifications[0]->deltaMass - ChemistryConstants::$C - ChemistryConstants::$O;
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
				$deltaMass = end($this->modifications)->deltaMass + ChemistryConstants::$H + 2 * ChemistryConstants::$O + ChemistryConstants::$C;
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
				$deltaMass = end($this->modifications)->deltaMass + ChemistryConstants::$O - ChemistryConstants::$N + ChemistryConstants::$H;
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
		
		for ($i = $this->charge - 1; $i > 0; $i--) {
			array_push($returnArray, new PeptideFragment($type, $neutralLoss, $length, ($mass + ChemistryConstants::$Proton * $i)/$i, $subPeptide, $i));
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
		$this->mz = ($mass + ChemistryConstants::$Proton * $originalCharge) / $charge;
		// signals precursor
		if ($originalCharge == 1) {
			$this->number = "+" . "H";
		} else {
			$this->number = "+" . "$originalCharge" . "H";
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
	public $sn;
	public $matchedFeatures;
	
	function Peak($feature, $basePeak) {
		$this->mz = $feature->mZ;
		$this->intensity = $feature->intensity;
		$this->percentBasePeak = 100 * $feature->intensity / $basePeak->intensity;
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
	public $site;
	public $deltaElement;
	public $deltaMass;
	
	function Modification($mod, $site) {
		if ($mod === null) {
			$this->site = $site;
			$this->deltaMass = 0;
		}
		else {
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

// $data->peakData
$data = json_decode(file_get_contents("php://input"));

if (empty($data->sequence)) {
	echo json_encode(array("error" => "Sequence is empty"));
} elseif (empty($data->charge)) {
	echo json_encode(array("error" => "Charge is undefined"));
} elseif (empty($data->fragmentTypes)) {
	echo json_encode(array("error" => "No Fragments were selected"));
} elseif (empty($data->peakData[0]->mZ)) {
	echo json_encode(array("error" => "No mass/charge data has been detected"));
} elseif (empty($data->peakData[0]->intensity)) {
	echo json_encode(array("error" => "No intensity data has been detected"));
} elseif (isset($peakData[0]->sN) && empty($data->peakData[0]->sn)) {
	echo json_encode(array("error" => "No signal to noise data has been detected"));
} elseif ($data->matchingType == "S/N" && !isset($peakData[0]->sN)) {
	echo json_encode(array("error" => "Cannot filter by signal to noise if it is not provided"));
} elseif (empty($data->tolerance)) {
	echo json_encode(array("error" => "No mass tolerance for fragment matching has been detected"));
} elseif($data->tolerance < 0) {
	echo json_encode(array("error" => "Mass tolerance must be greater than zero"));
} elseif($data->cutoff < 0) {
	echo json_encode(array("error" => "Matching threshold must be positive or zero"));
} else {
	$basePeak = BasePeak($data->peakData);
	// all data is good. process peptide
	$peptide = new Peptide($data->sequence, $data->precursorCharge, $data->charge, $data->fragmentTypes, $data->tolerance, $data->toleranceType, $data->matchingType, $data->cutoff, $basePeak);
	if (empty($data->mods)) {
		$data->mods = array();
	} 
	$peptide->AddMods($data->mods);
	$peptide->AddPeaks($data->peakData, $basePeak);
	$peptide->precursorMass = $peptide->CalculatePrecursorMass();
	// generate all theoretical peptide fragments
	$peptide->CalculateFragmentMZs();
	// match these fragments to the submitted data
	$peptide->MatchFragments();

	echo json_encode($peptide);
}
?>