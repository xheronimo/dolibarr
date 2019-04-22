<?php
/* Copyright (C) 2015       Laurent Destailleur     <eldy@users.sourceforge.net>
  * Copyright (C) 2015       Alexandre Spangaro      <aspangaro@open-dsi.fr>
 * Copyright (C) 2016-2018  Philippe Grand          <philippe.grand@atoo-net.com>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2018       Francis Appels          <francis.appels@z-application.com>
 * Copyright (C) 2019       Markus Welters          <markus@welters.de>
 * Copyright (C) 2019       Rafael Ingenleuf        <ingenleuf@welters.de>
 * Copyright (C) 2019       Javier Gomez      	    <javi_direc@hotmail.com>
 *
 

 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/expensereport/doc/pdf_standard.modules.php
 *	\ingroup    expensereport
 *	\brief      File of class to generate expense report from standard model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/expensereport/modules_expensereport.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/userbankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/funciones_pdf.php';



/**
 *	Class to generate expense report based on standard model
 */
class pdf_expenser extends ModeleExpenseReport
{
     /**
     * @var DoliDb Database handler
     */
    public $db;

	/**
     * @var string model name
     */
    public $name;

	/**
     * @var string model description (short text)
     */
    public $description;

	/**
     * @var string document type
     */
    public $type;

    /**
     * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.4 = array(5, 4)
     */
	public $phpmin = array(5, 4);

	/**
     * Dolibarr version of the loaded document
     * @var string
     */
	public $version = 'dolibarr';

	/**
     * @var int page_largeur
     */
    public $page_largeur;

	/**
     * @var int page_hauteur
     */
    public $page_hauteur;

	/**
     * @var array format
     */
    public $format;

	/**
     * @var int marge_gauche
     */
	public $marge_gauche;

	/**
     * @var int marge_droite
     */
	public $marge_droite;

	/**
     * @var int marge_haute
     */
	public $marge_haute;

	/**
     * @var int marge_basse
     */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Company object that emits
	 */
	public $emetteur;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf, $langs, $mysoc, $user;

		// Translations
		$langs->loadLangs(array("main", "trips", "projects"));

		$this->db = $db;
		$this->name = " pdf_expenser";
		$this->description = $langs->trans('PDFStandardExpenseReports');

		// Dimension page pour format A4
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Affiche mode reglement
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 0;                // Affiche si il y a eu escompte
		$this->option_credit_note = 0;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		$this->franchise = !$mysoc->tva_assuj;
		
		// Get source company
		$this->emetteur=$mysoc;
	
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang, -2);    // By default, if was not defined

		// Define position of columns
		$this->posxpiece=$this->marge_gauche+1;
		$this->posxcomment=$this->marge_gauche+10;
		//$this->posxdate=88;
		//$this->posxtype=107;
		//$this->posxprojet=120;
		$this->posxtva=130;
		$this->posxup=145;
		$this->posxqty=168;
		$this->postotalttc=178;
        // if (empty($conf->projet->enabled)) {
        //     $this->posxtva-=20;
        //     $this->posxup-=20;
        //     $this->posxqty-=20;
        //     $this->postotalttc-=20;
        // }
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$this->posxdate-=20;
			$this->posxtype-=20;
			$this->posxprojet-=20;
			$this->posxtva-=20;
			$this->posxup-=20;
			$this->posxqty-=20;
			$this->postotalttc-=20;
		}

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
	}


    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Function to build pdf onto disk
     *
     *  @param		Object		$object				Object to generate
     *  @param		Translate	$outputlangs		Lang output object
     *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
     *  @param		int			$hidedetails		Do not show line details
     *  @param		int			$hidedesc			Do not show desc
     *  @param		int			$hideref			Do not show ref
     *  @return     int             				1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
        // phpcs:enable
		global $user, $langs, $conf, $mysoc, $db, $hookmanager;

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';

		// Load traductions files requiredby by page
		$outputlangs->loadLangs(array("main", "trips", "projects", "dict", "bills", "banks"));

		$nblignes = count($object->lines);

		if ($conf->expensereport->dir_output) {
			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->expensereport->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->expensereport->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}

			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (! is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks

				// Create pdf instance
				$pdf=pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
				$heightforinfotot = 40;	// Height reserved to output the info and total part
		        $heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT)?$conf->global->MAIN_PDF_FREETEXT_HEIGHT:5);	// Height reserved to output the free text on last page
		        $heightforfooter = $this->marge_basse + 12;	// Height reserved to output the footer (value include bottom margin)
	            if ($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS >0) $heightforfooter+= 6;

	            $pdf->SetAutoPageBreak(1, 0);

                if (class_exists('TCPDF'))
                {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));
			    // Set path to the background PDF File
                if (! empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
                {
                    $pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
                    $tplidx = $pdf->importPage(1);
                }

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Trips"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Trips"));
				if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

				// New page
				$pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				_pagehead($pdf, $object, 1, $outputlangs, $this, 2);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 95;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?65:10);
				$tab_height = 130;
				$tab_height_newpage = 150;

				// Show notes
				$notetoshow=empty($object->note_public)?'':$object->note_public;
				if (! empty($conf->global->MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE))
				{
					// Get first sale rep
					if (is_object($object->thirdparty))
					{
						$salereparray=$object->thirdparty->getSalesRepresentatives($user);
						$salerepobj=new User($this->db);
						$salerepobj->fetch($salereparray[0]['id']);
						if (! empty($salerepobj->signature)) $notetoshow=dol_concatdesc($notetoshow, $salerepobj->signature);
					}
				}
				if ($notetoshow)
				{
					$substitutionarray=pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);

					$tab_top = 95;

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxpiece-1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
					$nexY = $pdf->GetY();
					$height_note=$nexY-$tab_top;

					// Rect prend une longueur en 3eme param
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->Rect($this->marge_gauche, $tab_top-1, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $height_note+1);

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY+6;
				}
				else
				{
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$initialY = $tab_top + 7;
				$nexY = $tab_top + 7;

				// Loop on each lines
				for ($i = 0 ; $i < $nblignes ; $i++) {
					$pdf->SetFont('', '', $default_font_size - 2);   // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					$pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', 1, $heightforfooter+$heightforfreetext+$heightforinfotot);	// The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();
                    $curY = $nexY;
                    $pdf->startTransaction();
                    $this->printLine($pdf, $object, $i, $curY, $default_font_size, $outputlangs, $hidedetails);
                    $pageposafter=$pdf->getPage();
					if ($pageposafter > $pageposbefore) {
                        // There is a pagebreak
						$pdf->rollbackTransaction(true);
						$pageposafter = $pageposbefore;
						//print $pageposafter.'-'.$pageposbefore;exit;
						$pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
						$this->printLine($pdf, $object, $i, $curY, $default_font_size, $outputlangs, $hidedetails);
						$pageposafter = $pdf->getPage();
						$posyafter = $pdf->GetY();
						//var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
						if ($posyafter > ($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))) {
                            // There is no space left for total+free text
							if ($i == ($nblignes-1)) {
                                // No more lines, and no space left to show total, so we create a new page
								$pdf->AddPage('', '', true);
								if (! empty($tplidx)) $pdf->useTemplate($tplidx);
								if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) _pagehead($pdf, $object, 0, $outputlangs, $this, 2);
								$pdf->setPage($pageposafter+1);
							}
						}
						else
						{
							// We found a page break
							$showpricebeforepagebreak=0;
						}
					}
					else	// No pagebreak
					{
						$pdf->commitTransaction();
					}
                    //nexY
                    $nexY = $pdf->GetY();
                    $pageposafter=$pdf->getPage();
                    $pdf->setPage($pageposbefore);
                    $pdf->setTopMargin($this->marge_haute);
                    $pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

                    //$nblineFollowComment = 1;
                    // Cherche nombre de lignes a venir pour savoir si place suffisante
					// if ($i < ($nblignes - 1))	// If it's not last line
					// {
					//     //Fetch current description to know on which line the next one should be placed
					// 	$follow_comment = $object->lines[$i]->comments;
					// 	$follow_type = $object->lines[$i]->type_fees_code;

					// 	//on compte le nombre de ligne afin de verifier la place disponible (largeur de ligne 52 caracteres)
					// 	$nbLineCommentNeed = dol_nboflines_bis($follow_comment,52,$outputlangs->charset_output);
					// 	$nbLineTypeNeed = dol_nboflines_bis($follow_type,4,$outputlangs->charset_output);

                    //     $nblineFollowComment = max($nbLineCommentNeed, $nbLineTypeNeed);
					// }

                    //$nexY+=$nblineFollowComment*($pdf->getFontSize()*1.3);    // Passe espace entre les lignes
                    $nexY += ($pdf->getFontSize()*1.3);    // Passe espace entre les lignes

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == 1)
						{
							_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code, $this, 2);
						}
						else
						{
							_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code, $this, 2);
		
						}
						_pagefoot($pdf,$object,$outputlangs,1, $this, 2);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) _pagehead($pdf, $object, 0, $outputlangs, $this, 2);
					}
					if (isset($object->lines[$i+1]->pagebreak) && $object->lines[$i+1]->pagebreak)
					{
						if ($pagenb == 1)
						{
							_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code, $this, 2);
						}
						else
						{
							_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code, $this, 2);
						}
						_pagefoot($pdf,$object,$outputlangs, 1, $this, 2);
						// New page
						$pdf->AddPage();
						if (! empty($tplidx)) $pdf->useTemplate($tplidx);
						$pagenb++;
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) _pagehead($pdf, $object, 0, $outputlangs, $this, 2);
					}
				}

				// Show square
				if ($pagenb == 1)
				{
					_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code, $this, 2);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}
				else
				{
					_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code, $this, 2);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				$pdf->SetFont('','', 10);
            	// Show total area box
				$posy=$bottomlasttab+5;
				$posy_start_of_totals = $posy;
				$pdf->SetXY(130, $posy);
				$pdf->MultiCell(70, 5, $outputlangs->transnoentities("TotalHT"), 1, 'L');
				$pdf->SetXY(180, $posy);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - 180, 5, price($object->total_ht), 1, 'R');
				$pdf->SetFillColor(248, 248, 248);

				if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
				{
				    // TODO Show vat amout per tax level
					$posy+=5;
					$pdf->SetXY(130, $posy);
					$pdf->SetTextColor(0, 0, 60);
					$pdf->MultiCell(70, 5, $outputlangs->transnoentities("TotalVAT"), 1, 'L');
					$pdf->SetXY(180, $posy);
					$pdf->MultiCell($this->page_largeur - $this->marge_gauche - 180, 5, price($object->total_tva), 1, 'R');
				}

				$posy+=5;
				$pdf->SetXY(130, $posy);
				$pdf->SetFont('', 'B', 10);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell(70, 5, $outputlangs->transnoentities("TotalTTC"), 1, 'L');
				$pdf->SetXY(180, $posy);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - 180, 5, price($object->total_ttc), 1, 'R');

				// show payments zone
				$sumPayments = $object->getSumPayments();
				if ($sumPayments > 0 && empty($conf->global->PDF_EXPENSEREPORT_NO_PAYMENT_DETAILS)) {
					$posy=$this->tablePayments($pdf, $object, $posy_start_of_totals, $outputlangs);
				}

				// Pied de page
				_pagefoot($pdf, $object, $outputlangs, 0, $this, 2);
				if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPage();

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
				{
				    $this->error = $hookmanager->error;
				    $this->errors = $hookmanager->errors;
				}

				if (! empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				$this->result = array('fullpath'=>$file);

				return 1;   // Pas d'erreur
			}
			else
			{
				$this->error=$langs->trans("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->trans("ErrorConstantNotDefined", "EXPENSEREPORT_OUTPUTDIR");
			return 0;
		}
	}

    /**
     * @param   TCPDF       $pdf                Object PDF
     * @param   Object      $object             Object to show
     * @param   int         $linenumber         line number
     * @param   int         $curY               current y position
     * @param   int         $default_font_size  default siez of font
     * @param   Translate   $outputlangs        Object lang for output
     * @param	int			$hidedetails		Hide details (0=no, 1=yes, 2=just special lines)
     * @return  void
     */
	private function printLine(&$pdf, $object, $linenumber, $curY, $default_font_size, $outputlangs, $hidedetails = 0)
	{
        global $conf;
        $pdf->SetFont('', '', $default_font_size - 1);

        // Accountancy piece
        $pdf->SetXY($this->posxpiece, $curY);
        $pdf->writeHTMLCell($this->posxcomment-$this->posxpiece-0.8, 4, $this->posxpiece-1, $curY, $linenumber + 1, 0, 1);

        // Date
        //$pdf->SetXY($this->posxdate -1, $curY);
        //$pdf->MultiCell($this->posxtype-$this->posxdate-0.8, 4, dol_print_date($object->lines[$linenumber]->date,"day",false,$outputlangs), 0, 'C');

        // Type
        $pdf->SetXY($this->posxtype -1, $curY);
        $nextColumnPosX = $this->posxup;
        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT)) {
            $nextColumnPosX = $this->posxtva;
        }
        if (!empty($conf->projet->enabled)) {
            $nextColumnPosX = $this->posxprojet;
        }

        $expensereporttypecode = $object->lines[$linenumber]->type_fees_code;
        $expensereporttypecodetoshow = $outputlangs->trans($expensereporttypecode);
        if ($expensereporttypecodetoshow == $expensereporttypecode) {
            $expensereporttypecodetoshow = preg_replace('/^(EX_|TF_)/', '', $expensereporttypecodetoshow);
        }
        //$expensereporttypecodetoshow = dol_trunc($expensereporttypecodetoshow, 9);

        //$pdf->MultiCell($nextColumnPosX-$this->posxtype-0.8, 4, $expensereporttypecodetoshow, 0, 'C');

        // Project
        //if (! empty($conf->projet->enabled))
        //{
        //    $pdf->SetFont('','', $default_font_size - 1);
        //    $pdf->SetXY($this->posxprojet, $curY);
        //    $pdf->MultiCell($this->posxtva-$this->posxprojet-0.8, 4, $object->lines[$linenumber]->projet_ref, 0, 'C');
        //}

        // VAT Rate
        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT)) {
            $vat_rate = pdf_getlinevatrate($object, $linenumber, $outputlangs, $hidedetails);
            $pdf->SetXY($this->posxtva, $curY);
            $pdf->MultiCell($this->posxup-$this->posxtva-0.8, 4, $vat_rate, 0, 'R');
        }

        // Unit price
        $pdf->SetXY($this->posxup, $curY);
        $pdf->MultiCell($this->posxqty-$this->posxup-0.8, 4, price($object->lines[$linenumber]->value_unit), 0, 'R');

        // Quantity
        $pdf->SetXY($this->posxqty, $curY);
        $pdf->MultiCell($this->postotalttc-$this->posxqty-0.8, 4, $object->lines[$linenumber]->qty, 0, 'R');

        // Total with all taxes
        $pdf->SetXY($this->postotalttc-1, $curY);
        $pdf->MultiCell($this->page_largeur-$this->marge_droite-$this->postotalttc, 4, price($object->lines[$linenumber]->total_ttc), 0, 'R');

        // Comments
        $pdf->SetXY($this->posxcomment, $curY);
          $comment = $outputlangs->trans("Date").':'. dol_print_date($object->lines[$linenumber]->date, "day", false, $outputlangs).' ';
        $comment .= $outputlangs->trans("Type").':'. $expensereporttypecodetoshow.'<br>';
        if (! empty($object->lines[$linenumber]->projet_ref)) {
            $comment .= $outputlangs->trans("Project").':'. $object->lines[$linenumber]->projet_ref.'<br>';
        }
        $comment .= $object->lines[$linenumber]->comments;
        $pdf->writeHTMLCell($this->posxtva-$this->posxcomment-0.8, 4, $this->posxcomment-1, $curY, $comment, 0, 1);
    }

