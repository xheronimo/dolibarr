<?php
/* Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2008      Raphael Bertrand     <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2015 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2012      Christophe Battarel  <christophe.battarel@altairis.fr>
 * Copyright (C) 2012      Cedric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2017-2018 Ferran Marcet        <fmarcet@2byte.es>
 * Copyright (C) 2018      Frédéric France      <frederic.france@netlogic.fr>
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
		$tipo = 0 proposal
		$tipo = 1 facture	
		$tipo = 2 expenser
		$tipo = 3 PDFCommandes
	*/
	
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.NotCamelCaps
	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	function _tableau_info(&$pdf, $object, $posy, $outputlangs, &$pagina, $tipo)
	{
        // phpcs:enable
		global $conf;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($pagina->emetteur->country_code == 'FR' && $pagina->franchise == 1)
		{
			print_texto($pdf, $pagina->marge_gauche, $posy, 100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0, '', 'B', $default_font_size - 2);
			$posy=$pdf->GetY()+4;
		}

		$posxval=52;

        // Show shipping date
		if (($tipo == 0) || ($tipo ==3))
		{
			if (! empty($object->date_livraison))
			{
				$outputlangs->load("sendings");
				print_texto($pdf, $pagina->marge_gauche, $posy, 80, 4, $outputlangs->transnoentities("DateDeliveryPlanned"), 0, 'L', 0, '', 'B', $default_font_size - 2);
				$dlp=dol_print_date($object->date_livraison, "daytext", false, $outputlangs, true);
				print_texto($pdf, $posxval, $posy, 80, 4, $dlp, 0, 'L', 0, '', '', $default_font_size - 2);	
				$posy=$pdf->GetY()+1;
			}
			elseif ($object->availability_code || $object->availability)    // Show availability conditions
			{
				print_texto($pdf, $pagina->marge_gauche, $posy, 80, 4, $outputlangs->transnoentities("AvailabilityPeriod"), 0, 'L', 0, '', 'B', $default_font_size - 2);			
				$pdf->SetTextColor(0, 0, 0);
				$lib_availability=$outputlangs->transnoentities("AvailabilityType".$object->availability_code)!=('AvailabilityType'.$object->availability_code)?$outputlangs->transnoentities("AvailabilityType".$object->availability_code):$outputlangs->convToOutputCharset($object->availability);
				$lib_availability=str_replace('\n', "\n", $lib_availability);
				print_texto($pdf, $posxval, $posy, 80, 4, $lib_availability, 0, 'L', 0, '', '', $default_font_size - 2);
				$posy=$pdf->GetY()+1;
			}
		}
		
		// Show payments conditions
		
		if ((($tipo == 1) && ($object->type != 2)) 
			|| (($tipo == 0) && (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMCOND))) && ($object->cond_reglement_code || $object->cond_reglement)
			|| (($tipo ==3) && ($object->cond_reglement_code || $object->cond_reglement)))
		{
			print_texto($pdf, $pagina->marge_gauche, $posy, 43, 4, $outputlangs->transnoentities("PaymentCondition"), 0, 'L', 0, '', 'B', $default_font_size - 2);	
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n', "\n", $lib_condition_paiement);
			print_texto($pdf, $posxval, $posy, 67, 4, $lib_condition_paiement, 0, 'L', 0, '', '', $default_font_size - 2);
			$posy=$pdf->GetY()+3;
		}
		if  ((($object->type != 2) && ($tipo ==1)) 
			|| ($tipo == 0) || ($tipo ==3))
		{
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
			&& empty($conf->global->FACTURE_CHQ_NUMBER)
			&& empty($conf->global->FACTURE_RIB_NUMBER))
			{
				$pagina->error = $outputlangs->transnoentities("ErrorNoPaiementModeConfigured");
			}
			// Avoid having any valid PDF with setup that is not complete
			elseif ((($tipo == 0) && (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMMODE))) 
			|| (($tipo == 1) && (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ'))
			|| (($tipo ==3) && (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')))

			{
			// Show payment mode
				if ($object->mode_reglement_code
				&& $object->mode_reglement_code != 'CHQ'
				&& $object->mode_reglement_code != 'VIR')
				{
					print_texto($pdf, $pagina->marge_gauche, $posy, 80, 5, $outputlangs->transnoentities("PaymentMode"), 0, 'L', 0, '', 'B', $default_font_size - 2);	
					$lib_mode_reg=$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$outputlangs->convToOutputCharset($object->mode_reglement);
					print_texto($pdf, $posxval, $posy, 80, 5, $lib_mode_reg, 0, 'L', 0, '', '', $default_font_size - 2);
					$posy=$pdf->GetY()+2;
				}

				// Show payment mode CHQ
				if (((($tipo == 0) && (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMMODE))) 
				|| (($tipo == 1) ||($tipo ==3))) && (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ'))
				{
					// Si mode reglement non force ou si force a CHQ
					if (! empty($conf->global->FACTURE_CHQ_NUMBER))
					{
						$diffsizetitle=(empty($conf->global->PDF_DIFFSIZE_TITLE)?3:$conf->global->PDF_DIFFSIZE_TITLE);

						if ($conf->global->FACTURE_CHQ_NUMBER > 0)
						{
							$account = new Account($pagina->db);
							$account->fetch($conf->global->FACTURE_CHQ_NUMBER);
							print_texto($pdf, $pagina->marge_gauche, $posy, 100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio), 0, 'L', 0, '', 'B', $default_font_size - $diffsizetitle);	
							$posy=$pdf->GetY()+1;

							if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
							{
								print_texto($pdf, $pagina->marge_gauche, $posy, 100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0, '', 'B', $default_font_size - $diffsizetitle);	
								$posy=$pdf->GetY()+2;
							}
						}
						if ($conf->global->FACTURE_CHQ_NUMBER == -1)
						{
							print_texto($pdf, $pagina->marge_gauche, $posy, 100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$pagina->emetteur->name), 0, 'L', 0, '', 'B', $default_font_size - $diffsizetitle);	
							$posy=$pdf->GetY()+1;

							if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
							{
								print_texto($pdf, $pagina->marge_gauche, $posy, 100, 3, $outputlangs->convToOutputCharset($pagina->emetteur->getFullAddress()), 0, 'L', 0, '', 'B', $default_font_size - $diffsizetitle);	
								$posy=$pdf->GetY()+2;
							}
						}
					}
				}

				// If payment mode not forced or forced to VIR, show payment with BAN
				if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
				{
					if (! empty($object->fk_account) || ! empty($object->fk_bank) || ! empty($conf->global->FACTURE_RIB_NUMBER))
					{	
						$bankid=(empty($object->fk_account)?$conf->global->FACTURE_RIB_NUMBER:$object->fk_account);
						if (! empty($object->fk_bank)) $bankid=$object->fk_bank;   // For backward compatibility when object->fk_account is forced with object->fk_bank
						$account = new Account($pagina->db);
						$account->fetch($bankid);

						$curx=$pagina->marge_gauche;
						$cury=$posy;

						$posy=pdf_bank($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);
	
						$posy+=2;
					}
				}
			}
		}

		return $posy;
	}
    
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Show total to pay
	 *
	 *	@param	PDF			$pdf            Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, &$pagina, $tipo)
	{
        // phpcs:enable
		global $conf,$mysoc;

        $sign=1;
		if ($tipo ==1) {
			if ($object->type == 2 && ! empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign=-1;
		}
		
        $default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', '', $default_font_size - 1);

		// Tableau total
		$col1x = 120; $col2x = 170;
		if ($pagina->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}
		$largcol2 = ($pagina->page_largeur - $pagina->marge_droite - $col2x);

		$useborder=0;
		$index = 0;

		// Total HT
		$pdf->SetFillColor(255,255,255);
		print_texto1($pdf, $col1x, $tab2_top + 0, $col2x-$col1x, $tab2_hl,$outputlangs->transnoentities("TotalHT"), 0, 'L', 1);	
		$total_ht = (($conf->multicurrency->enabled && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
		print_texto1($pdf, $col2x, $tab2_top + 0, $largcol2, $tab2_hl, price($sign * ($total_ht + (! empty($object->remise)?$object->remise:0)), 0, $outputlangs), 0, 'R', 1);	

		// Show VAT by rates and total
		$pdf->SetFillColor(248, 248, 248);

		$total_ttc = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$pagina->atleastoneratenotnull=0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$tvaisnull=((! empty($pagina->tva) && count($pagina->tva) == 1 && isset($pagina->tva['0.000']) && is_float($pagina->tva['0.000'])) ? true : false);
			if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL) && $tvaisnull)
			{
				// Nothing to do
			}
			else
			{
			    // FIXME amount of vat not supported with multicurrency

				//Local tax 1 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
					foreach( $pagina->localtax1 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('1', '3', '5'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								//$pagina->atleastoneratenotnull++;

								$index++;
								$tvacompl='';
								if (preg_match('/\*/', $tvakey))
								{
									$tvakey=str_replace('*', '', $tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code).' ';
								$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
								print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);	
								print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
							}
						}
					}
	      		//}
				//Local tax 2 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
					foreach( $pagina->localtax2 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('1', '3', '5'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								//$pagina->atleastoneratenotnull++;

								$index++;								
								$tvacompl='';
								if (preg_match('/\*/', $tvakey))
								{
									$tvakey=str_replace('*', '', $tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';
								$totalvat.=vatrate(abs($tvakey), 1).$tvacompl;
								print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);	
								print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);							
							}
						}
					}

                //}

				// VAT
				if ($tipo ==1)
				{
					// Situations totals migth be wrong on huge amounts
					if ($object->situation_cycle_ref && $object->situation_counter > 1)
						{
						$sum_pdf_tva = 0;
						foreach($pagina->tva as $tvakey => $tvaval)
						{
							$sum_pdf_tva+=$tvaval; // sum VAT amounts to compare to object
						}

						if($sum_pdf_tva!=$object->total_tva) 
						{ // apply coef to recover the VAT object amount (the good one)
							$coef_fix_tva = $object->total_tva / $sum_pdf_tva;

							foreach($pagina->tva as $tvakey => $tvaval) 
							{
								$pagina->tva[$tvakey]=$tvaval * $coef_fix_tva;
							}
						}
					}
				}

				foreach($pagina->tva as $tvakey => $tvaval)
				{
					if ($tvakey != 0)    // On affiche pas taux 0
					{
						$pagina->atleastoneratenotnull++;

						$index++;
						$tvacompl='';
						if (preg_match('/\*/', $tvakey))
						{
							$tvakey=str_replace('*', '', $tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat =$outputlangs->transcountrynoentities("TotalVAT",$mysoc->country_code).' ';
						$totalvat.=vatrate($tvakey,1).$tvacompl;
						print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);	
						print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);							
					}
				}

				//Local tax 1 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
					foreach( $pagina->localtax1 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('2', '4', '6'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey != 0)    // On affiche pas taux 0
							{
								//$pagina->atleastoneratenotnull++;

								$index++;
								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code).' ';
								$totalvat.=vatrate(abs($tvakey), 1).$tvacompl;
								print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);	
								print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);							
							}
						}
					}
	      		//}
				//Local tax 2 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
					foreach( $pagina->localtax2 as $localtax_type => $localtax_rate)
					{
						if (in_array((string) $localtax_type, array('2', '4', '6'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
						    // retrieve global local tax
							if ($tvakey != 0)    // On affiche pas taux 0
							{
								//$pagina->atleastoneratenotnull++;

								$index++;
								$tvacompl='';
								if (preg_match('/\*/', $tvakey))
								{
									$tvakey=str_replace('*', '', $tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';

								$totalvat.=vatrate(abs($tvakey), 1).$tvacompl;
								print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);	
								print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);							
							}
						}
					//}
				}

				// Revenue stamp
				if ($tipo ==1)
				{
				if (price2num($object->revenuestamp) != 0)
					{
						$index++;
						print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RevenueStamp"), $useborder, 'L', 1);	
						print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($sign * $object->revenuestamp), $useborder, 'R', 1);							
					}
				}

				// Total TTC
				$index++;
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFillColor(224, 224, 224);
				print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L', 1);	
				print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($sign * $total_ttc, 0, $outputlangs), $useborder, 'R', 1);							
			}
		}

		$pdf->SetTextColor(0, 0, 0);
		if ($tipo == 1) 
		{
			$creditnoteamount=$object->getSumCreditNotesUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);	// Warning, this also include excess received
			$depositsamount=$object->getSumDepositsUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);
			//print "x".$creditnoteamount."-".$depositsamount;exit;
			$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		}
		else 
		{	
			if ($tipo == 0) {
				$resteapayer = $object->total_ttc - $deja_regle;
			} else{
				$resteapayer = price2num($total_ttc - $deja_regle , 'MT');
			}
		}
		if (! empty($object->paye)) $resteapayer=0;

		if (($deja_regle > 0 ) 
			|| ((($tipo == 1) && ($creditnoteamount > 0 || $depositsamount > 0)) && empty($conf->global->INVOICE_NO_PAYMENT_DETAILS)))
		{
			// Already paid + Deposits
			$index++;
			if (($tipo == 3) || ($tipo == 0))
				{
					$texto1 = $outputlangs->transnoentities("AlreadyPaid");
				}else{
					$texto1 = $outputlangs->transnoentities("Paid");
				}
			print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $texto1, 0, 'L', 0);					
			
			if ($tipo == 1)	
			{
				$texto1 = price($deja_regle + $depositsamount, 0, $outputlangs);
			}
			else 
			{
				$texto1 = price($deja_regle, 0, $outputlangs);
			}
			print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, $texto1, 0, 'R', 1);	
	
			// Credit note
			if ($tipo == 1)
			{
				if ($creditnoteamount)
				{
					$labeltouse = ($outputlangs->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangs->transnoentities("CreditNotesOrExcessReceived") : $outputlangs->transnoentities("CreditNotes");
					$index++;
					print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $labeltouse, 0, 'L', 1);	
					print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($creditnoteamount, 0, $outputlangs), 0, 'R', 1);							
				}

				// Escompte
				if (($object->close_code == Facture::CLOSECODE_DISCOUNTVAT) || ($object->close_code == 'discount_vat'))
				{
					$index++;
					$pdf->SetFillColor(255,255,255);
					print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', 0);					
					if ($tipo == 0)
					{
						$texto1 = price($object->total_ttc - $deja_regle, 0, $outputlangs);
					}
					else 
					{
						$texto1 = price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 0, $outputlangs);
					}		
					print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, $texto1, $useborder, 'R', 1);	
					
					$resteapayer=0;
				}
			}

			$index++;
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			print_texto1($pdf, $col1x, $tab2_top + $tab2_hl * $index, $col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 0);					
			print_texto1($pdf, $col2x, $tab2_top + $tab2_hl * $index, $largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);	
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	/**
	 *   Show table for lines
	 *
	 *   @param		PDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0, $currency='', &$pagina, $tipo)
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) $hidetop=-1;

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$currency));
			print_texto1($pdf, $pagina->page_largeur - $pagina->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top-4,($pdf->GetStringWidth($titre) + 3), 2, $titre,0, 'J', 0);					

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (! empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) $pdf->Rect($pagina->marge_gauche, $tab_top, $pagina->page_largeur-$pagina->marge_droite-$pagina->marge_gauche, 5, 'F', null, explode(',',$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$pagina->printRect($pdf,$pagina->marge_gauche, $tab_top, $pagina->page_largeur-$pagina->marge_gauche-$pagina->marge_droite, $tab_height, $hidetop, $hidebottom);	// Rect prend une longueur en 3eme param et 4eme param

		if (empty($hidetop))
		{
			$pdf->line($pagina->marge_gauche, $tab_top+5, $pagina->page_largeur-$pagina->marge_droite, $tab_top+5);	// line prend une position y en 2eme param et 4eme param
		if ($tipo == 2)
		{

			// Accountancy piece
			if (empty($hidetop)) 
			{
				print_texto1($pdf, $pagina->posxpiece-1, $tab_top+1, $pagina->posxcomment-$pagina->posxpiece-1, 1, '', '', 'R', 0);	
			}		

		// Comments
			$pdf->line($pagina->posxcomment-1, $tab_top, $pagina->posxcomment-1, $tab_top + $tab_height);
			if (empty($hidetop)) 
			{
				print_texto1($pdf, $pagina->posxcomment-1, $tab_top+1, $pagina->posxdate-$pagina->posxcomment-1, 1, $outputlangs->transnoentities("Description"), '', 'L', 0);	
			}
		}
		else {
			print_texto1($pdf, $pagina->posxdesc-1, $tab_top+1, 108, 2, $outputlangs->transnoentities("Designation"), '', 'L', 0);	
			}
		}
		if ($tipo ==1) {
			$texto = $conf->global->MAIN_GENERATE_INVOICES_WITH_PICTURE;
		}
		elseif ($tipo !=2){
			$texto = $conf->global->MAIN_GENERATE_PROPOSALS_WITH_PICTURE;
			
		}
		if (! empty($texto))
		{
			$pdf->line($pagina->posxpicture-1, $tab_top, $pagina->posxpicture-1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				//$pdf->SetXY($pagina->posxpicture-1, $tab_top+1);
				//$pdf->MultiCell($pagina->posxtva-$pagina->posxpicture-1, 2, $outputlangs->transnoentities("Photo"),'','C');
			}
		}

		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN))
		{
			$pdf->line($pagina->posxtva -1, $tab_top, $pagina->posxtva -1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				print_texto1($pdf, $pagina->posxtva - 1, $tab_top + 1, $pagina->posxup - $pagina->posxtva - 1 , 2, $outputlangs->transnoentities("VAT"), '', 'C', 0);	
			}
		}

		$pdf->line($pagina->posxup-1, $tab_top, $pagina->posxup-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			print_texto1($pdf, $pagina->posxup-1, $tab_top + 1, $pagina->posxqty - $pagina->posxup - 1, 2, $outputlangs->transnoentities("PriceUHT"), '', 'C', 0);
		}

		$pdf->line($pagina->posxqty -1, $tab_top, $pagina->posxqty- 1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			if(($tipo ==1) && ($pagina->situationinvoice))
			{
				$posi1 = $pagina->posxprogress - $pagina->posxqty - 1;
			}
			elseif ($tipo ==2)
			 {
			 	$posi1 = $pagina->postotalttc - $pagina->posxqty - 1;			
			}			
			elseif ($conf->global->PRODUCT_USE_UNITS)
			{
				$posi1 = $pagina->posxunit - $pagina->posxqty - 1;				
			}
			else
			{
				$posi1 = $pagina->posxdiscount - $pagina->posxqty -1;
			}
			print_texto1($pdf, $pagina->posxqty -1, $tab_top + 1, $posi1, 2, $outputlangs->transnoentities("Qty"), '', 'C', 0);
	
		}
		
		if ($tipo ==2){
			$pdf->line($pagina->postotalttc, $tab_top, $pagina->postotalttc, $tab_top + $tab_height);
			if (empty($hidetop)) 
			{
				print_texto1($pdf, $pagina->postotalttc - 1, $tab_top + 1, $pagina->page_largeur - $pagina->marge_droite - $pagina->postotalttc, 2, $outputlangs->transnoentities("TotalTTC"), '', 'C', 0);
			}
		}
		else 
		{
			if (($tipo ==1) && ($pagina->situationinvoice)) 
			{
				$pdf->line($pagina->posxprogress - 1, $tab_top, $pagina->posxprogress - 1, $tab_top + $tab_height);
				if (empty($hidetop)) 
				{
					if($conf->global->PRODUCT_USE_UNITS)
					{
						$posi1 = $pagina->posxunit - $pagina->posxprogress;
					}
					elseif ($pagina->atleastonediscount)
					{
						$posi1 = $pagina->posxdiscount - $pagina->posxprogress;
					}
					else
					{
						$posi1 = $pagina->postotalht - $pagina->posxprogress;
					}
				print_texto1($pdf, $pagina->posxprogress, $tab_top + 1, $posi1, 2,  $outputlangs->transnoentities("Progress"), '', 'C', 0);
			}
		}

		if($conf->global->PRODUCT_USE_UNITS) {
			$pdf->line($pagina->posxunit - 1, $tab_top, $pagina->posxunit - 1, $tab_top + $tab_height);
			if (empty($hidetop)) 
			{
				print_texto1($pdf, $pagina->posxunit - 1, $tab_top + 1, $pagina->posxdiscount - $pagina->posxunit - 1, 2, $outputlangs->transnoentities("Unit"), '', 'C', 0);
			}
		}

		$pdf->line($pagina->posxdiscount - 1, $tab_top, $pagina->posxdiscount - 1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			if ($pagina->atleastonediscount)
			{
				print_texto1($pdf, $pagina->posxdiscount - 1, $tab_top + 1, $pagina->postotalht - $pagina->posxdiscount -1, 2, $outputlangs->transnoentities("ReductionShort"), '', 'C', 0);
			}
		}

		if (($tipo ==1) && ($pagina->situationinvoice))
		{
			$pdf->line($pagina->postotalht+4, $tab_top, $pagina->postotalht+4, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				print_texto1($pdf, $pagina->postotalht - 19, $tab_top + 1, 30, 2, $outputlangs->transnoentities("Situation"), '', 'C', 0);
			}
		}

		if ($pagina->atleastonediscount)
		{
			$pdf->line($pagina->postotalht, $tab_top, $pagina->postotalht, $tab_top + $tab_height);
		}
		if (empty($hidetop))
		{
			print_texto1($pdf, $pagina->postotalht - 1, $tab_top + 1, 30, 2, $outputlangs->transnoentities("TotalHT"), '', 'C', 0);
		}
	}
}
	
 
	/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	function _pagehead(&$pdf, $object, $showaddress, $outputlangs, &$pagina, $tipo, $titlekey="PdfOrderTitle")
	{
		global $conf, $langs, $hookmanager;

		// Load traductions files requiredby by page
		$outputlangs->loadLangs(array("main", "propal", "companies", "bills", "orders", "trips"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf,$outputlangs, $pagina->page_hauteur);

		//  Show Draft Watermark
		switch ($tipo){
			case 0:{
				$texto =$conf->global->PROPALE_DRAFT_WATERMARK;
				break;}
			case 1:{
				$texto =$conf->global->FACTURE_DRAFT_WATERMARK;
				break;}
			case 2:{
				$texto =$conf->global->EXPENSEREPORT_DRAFT_WATERMARK;
				break;}				
			case 3:{
				$texto =$conf->global->COMMANDE_DRAFT_WATERMARK;
				break;}	
		}

		if($object->statut== 0 && (! empty($texto)))
        {
		      pdf_watermark($pdf, $outputlangs, $pagina->page_hauteur, $pagina->page_largeur, 'mm', $texto);
        }

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy=$pagina->marge_haute;
		$posx=$pagina->page_largeur-$pagina->marge_droite-$w;

		$pdf->SetXY($pagina->marge_gauche, $posy);


		// Logo
		if (empty($conf->global->PDF_DISABLE_MYCOMPANY_LOGO))
		{
			$logo=$conf->mycompany->dir_output.'/logos/'.$pagina->emetteur->logo;
			if ($pagina->emetteur->logo)
			{
				if (is_readable($logo))
				{
				    $height=pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $pagina->marge_gauche, $posy, 0, $height);	// width=0 (auto)
				}
				else
				{
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
				}
			}
			else
			{
				$text=$pagina->emetteur->name;
				print_texto1($pdf, $pagina->marge_gauche, $posy, $w, 4, $outputlangs->convToOutputCharset($text), '', 'L', 0);
			}
		}

		$pdf->SetFont('', 'B',$default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		switch ($tipo){
			case 0:{
				$title=$outputlangs->transnoentities("PdfCommercialProposalTitle");
				break;
			}
			case 1:{
				$title=$outputlangs->transnoentities("PdfInvoiceTitle");
				if ($object->type == 1) $title=$outputlangs->transnoentities("InvoiceReplacement");
				if ($object->type == 2) $title=$outputlangs->transnoentities("InvoiceAvoir");
				if ($object->type == 3) $title=$outputlangs->transnoentities("InvoiceDeposit");
				if ($object->type == 4) $title=$outputlangs->transnoentities("InvoiceProForma");
				if ($pagina->situationinvoice) $title=$outputlangs->transnoentities("InvoiceSituation");
				break;
			}			
			case 2:{
				$title=$outputlangs->transnoentities("ExpenseReport");
				break;
			}
			case 3:{
				$title=$outputlangs->transnoentities($titlekey);
				break;
			}
		}
		print_texto($pdf, $posx, $posy, $w, 3, $outputlangs->convToOutputCharset($title), '', 'R', 0, '', 'B',$default_font_size + 3);			
		if ($tipo ==1)			
		{
			
			if ($object->statut == Facture::STATUS_DRAFT)
			{
				$pdf->SetTextColor(128,0,0);
				$textref.=' - '.$outputlangs->transnoentities("NotValidated");
			}
			else 
			{
				$textref=$outputlangs->transnoentities("Ref")." : " . $outputlangs->convToOutputCharset($object->ref);	
			}
			$posy+=5;
			$pdf->SetTextColor(0, 0, 60);
			print_texto1($pdf, $posx, $posy, $w, 4, $textref, '', 'R', 0);
		}

		$posy+=1;
		$pdf->SetFont('', '', $default_font_size - 2);

		if ($object->ref_client)
		{
			$posy+=4;
			$pdf->SetTextColor(0, 0, 60);
			print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("RefCustomer")." : " . $outputlangs->convToOutputCharset($object->ref_client), '', 'R', 0);
		}
//
		switch ($tipo){
			case 0:{
				$posy+=4;
				$pdf->SetTextColor(0, 0, 60);
				print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("Date")." : " . dol_print_date($object->date,"day",false,$outputlangs,true), '', 'R', 0);
				$posy+=4;
				$pdf->SetTextColor(0, 0, 60);
				print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("DateEndPropal")." : " . dol_print_date($object->fin_validite,"day",false,$outputlangs,true), '', 'R', 0);
				break;
			}
			case 1:{
				if (! empty($conf->global->INVOICE_POINTOFTAX_DATE))
				{
					$posy+=4;
					$pdf->SetTextColor(0, 0, 60);
					print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("DatePointOfTax")." : " . dol_print_date($object->date_pointoftax,"day",false,$outputlangs), '', 'R', 0);					
				}
				if ($object->type != 2)
				{
					$posy+=4;
					$pdf->SetTextColor(0, 0, 60);
					print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("DateDue")." : " . dol_print_date($object->date_lim_reglement,"day",false,$outputlangs,true), '', 'R', 0);					
				}
				break;	
			}				
			case 3:{				
				$posy+=4;
				$pdf->SetTextColor(0, 0, 60);
				print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("OrderDate")." : " . dol_print_date($object->date,"%d %b %Y",false,$outputlangs,true), '', 'R', 0);
				}
				break;
		}
		if (($tipo ==0) || ($tipo ==1)) 
		{
			if 	($object->thirdparty->code_client)
			{	
				$posy+=4;
				$pdf->SetTextColor(0, 0, 60);
				print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("CustomerCode")." : " . $outputlangs->transnoentities($object->thirdparty->code_client), '', 'R', 0);
			}
		}
		if ($tipo !=2){
		// Get contact
			if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP))
			{
				$arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
				if (count($arrayidcontact) > 0)
				{
					$usertmp=new User($pagina->db);
					$usertmp->fetch($arrayidcontact[0]);
					$posy+=4;
					$pdf->SetTextColor(0, 0, 60);
					print_texto1($pdf, $posx, $posy, $w, 3, $langs->trans("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R', 0);
				}
			}

			$posy+=1;

			$top_shift = 0;
			// Show list of linked objects
			$current_y = $pdf->getY();
			$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
			if ($current_y < $pdf->getY())
			{
				$top_shift = $pdf->getY() - $current_y;
			}
		}	
//
		if ($tipo ==2){
		// Date start period
			$posy+=5;
			$W = $pagina->page_largeur - $pagina->marge_droite - $posx;
			$pdf->SetTextColor(0, 0, 60);
			print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("DateStart")." : " . ($object->date_debut>0?dol_print_date($object->date_debut,"day",false,$outputlangs):''), '', 'R', 0);
			// Date end period
			$posy+=5;
			$pdf->SetTextColor(0, 0, 60);
			print_texto1($pdf, $posx, $posy, $w, 3, $outputlangs->transnoentities("DateEnd")." : " . ($object->date_fin>0?dol_print_date($object->date_fin,"day",false,$outputlangs):''), '', 'R', 0);
			
			// Status Expense Report
			$posy+=6;
			$pdf->SetTextColor(111, 81, 124);
			print_texto($pdf, $posx, $posy, $w, 3, $object->getLibStatut(0), '', 'R', 0,'', 'B', $default_font_size + 2);
			}

		if ($showaddress)
		{
			// Sender properties
			$carac_emetteur = pdf_build_address($outputlangs, $pagina->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
			$posy+=$top_shift;
			$posx=$pagina->marge_gauche;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$pagina->page_largeur-$pagina->marge_droite-80;

			$hautcadre=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 38 : 40;
			$widthrecbox=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 82;


			// Show sender frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posx, $posy-5);
			if  ($tipo == 2)
			{
				$texto = $outputlangs->transnoentities("TripSociete")." :";
			} 
			else
			{
				$texto = $outputlangs->transnoentities("BillFrom").":";
			}
			print_texto1($pdf, $posx, $posy - 5, 66, 5, $texto, '', 'L', 0);

			$pdf->SetFillColor(230, 230, 230);
			print_texto1($pdf, $posx, $posy , $widthrecbox, $hautcadre, "", '', 'R', 1);
			$pdf->SetTextColor(0, 0, 60);

			// Show sender name
			print_texto($pdf, $posx + 2, $posy + 3, $widthrecbox - 2, 4, $outputlangs->convToOutputCharset($pagina->emetteur->name), 0, 'L', 0, '', 'B', $default_font_size);

			$posy=$pdf->getY();

			// Show sender information
			print_texto($pdf, $posx + 2, $posy, $widthrecbox - 2, 4, $carac_emetteur, 0, 'L', 0, '', '', $default_font_size - 1);

			if ($tipo == 2)
			{
			// Show recipient
				$posy=50;
				$posx=100;

				// Show recipient frame
				$pdf->SetTextColor(0, 0, 0);
				print_texto($pdf, $posx, $posy - 5, 80, 5, $outputlangs->transnoentities("TripNDF")." :", 0, 'L', 0, '', 'B', 8);	
				$pdf->rect($posx, $posy, $pagina->page_largeur - $pagina->marge_gauche - $posx, $hautcadre);

				// Informations for trip (dates and users workflow)
				if ($object->fk_user_author > 0) 
				{
					$userfee=new User($pagina->db);
					$userfee->fetch($object->fk_user_author); $posy+=3;
					print_texto($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("AUTHOR")." : ".dolGetFirstLastname($userfee->firstname,$userfee->lastname), 0, 'L', 0, '', '', 10);	
					$posy+=5;
					print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("DateCreation")." : ".dol_print_date($object->date_create,"day",false,$outputlangs), 0, 'L', 0);
				}

				if ($object->fk_statut==99)
				{
					if ($object->fk_user_refuse > 0) {
						$userfee=new User($pagina->db);
						$userfee->fetch($object->fk_user_refuse); $posy+=6;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("REFUSEUR")." : ".dolGetFirstLastname($userfee->firstname,$userfee->lastname), 0, 'L', 0);
						$posy+=5;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("MOTIF_REFUS")." : ".$outputlangs->convToOutputCharset($object->detail_refuse), 0, 'L', 0);
						$posy+=5;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("DATE_REFUS")." : ".dol_print_date($object->date_refuse,"day",false,$outputlangs), 0, 'L', 0);
					}
				}
				elseif($object->fk_statut==4)
				{
					if ($object->fk_user_cancel > 0) {
						$userfee=new User($pagina->db);
						$userfee->fetch($object->fk_user_cancel); $posy+=6;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("CANCEL_USER")." : ".dolGetFirstLastname($userfee->firstname,$userfee->lastname), 0, 'L', 0);
						$posy+=5;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("MOTIF_CANCEL")." : ".$outputlangs->convToOutputCharset($object->detail_cancel), 0, 'L', 0);					
						$posy+=5;
						print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("DATE_CANCEL")." : ".dol_print_date($object->date_cancel,"day", false, $outputlangs), 0, 'L', 0);	
					}
				}
				else
				{
				if ($object->fk_user_approve > 0) {
					$userfee=new User($pagina->db);
					$userfee->fetch($object->fk_user_approve); $posy+=6;
					print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("VALIDOR")." : ".dolGetFirstLastname($userfee->firstname,$userfee->lastname), 0, 'L', 0);	
					$posy+=5;
					print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("DateApprove")." : ".dol_print_date($object->date_approve,"day", false, $outputlangs), 0, 'L', 0);	
				}
			}

			if($object->fk_statut==6) 
			{
				if ($object->fk_user_paid > 0) {
					$userfee=new User($pagina->db);
					$userfee->fetch($object->fk_user_paid); $posy+=6;
					print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("AUTHORPAIEMENT")." : ".dolGetFirstLastname($userfee->firstname,$userfee->lastname), 0, 'L', 0);	
					$posy+=5;
					print_texto1($pdf, $posx + 2, $posy, 96, 4, $outputlangs->transnoentities("DATE_PAIEMENT")." : ".dol_print_date($object->date_paiement,"day",false,$outputlangs), 0, 'L', 0);	
				}
			}
			}
			else
			{

				// If BILLING contact defined on invoice, we use it
				$usecontact=false;
				if ($tipo ==1)
				{ 
					$arrayidcontact=$object->getIdContact('external', 'BILLING');
				}
				else
				{
					$arrayidcontact=$object->getIdContact('external', 'CUSTOMER');
				}
				if (count($arrayidcontact) > 0)
				{
					$usecontact=true;
					$result=$object->fetch_contact($arrayidcontact[0]);
				}

				//Recipient name
				// On peut utiliser le nom de la societe du contact
				if ($usecontact && !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
					$thirdparty = $object->contact;
				}
				else 
				{
					$thirdparty = $object->thirdparty;
				}
	
				$carac_client_name= pdfBuildThirdpartyName($thirdparty, $outputlangs);

				$carac_client=pdf_build_address($outputlangs, $pagina->emetteur, $object->thirdparty, ($usecontact?$object->contact:''), $usecontact, 'target', $object);

				// Show recipient	
				$widthrecbox=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 100;
				if ($pagina->page_largeur < 210) $widthrecbox=84;	// To work with US executive format
				$posy=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
				$posy+=$top_shift;
				$posx=$pagina->page_largeur-$pagina->marge_droite-$widthrecbox;
				if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$pagina->marge_gauche;

				// Show recipient frame
				$pdf->SetTextColor(0 ,0, 0);
				print_texto($pdf, $posx + 2, $posy -5, $widthrecbox, 5, $outputlangs->transnoentities("BillTo").":", 0, 'L', 0, '', '', $default_font_size - 2);	
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

				// Show recipient name
				print_texto($pdf, $posx + 2, $posy +3, $widthrecbox, 2, $carac_client_name, 0, 'L', 0, '', 'B', $default_font_size);	
	
				$posy = $pdf->getY();

				// Show recipient information
				print_texto($pdf, $posx + 2, $posy , $widthrecbox, 4, $carac_client, 0, 'L', 0, '', '', $default_font_size - 1);	

			}
		}
		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}
 
/**
	 *   	Show footer of page. Need this->emetteur object
     *
	 *   	@param	PDF			$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, &$pagina, $tipo)
	{
		global $conf;
		$showdetails=$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		switch ($tipo){
			case 0:{
				$texto='PROPOSAL_FREE_TEXT';
				break;
			}
			case 1:{
				$texto='INVOICE_FREE_TEXT';
				break;
			}
			case 2:{
				$texto='EXPENSEREPORT_FREE_TEXT';
				break;
			}
			case 3:{
				$texto='ORDER_FREE_TEXT';
				break;
			}
		}			

		return pdf_pagefoot($pdf, $outputlangs, $texto, $pagina->emetteur, $pagina->marge_basse, $pagina->marge_gauche, $pagina->page_hauteur, $object, $showdetails, $hidefreetext);
	}

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Show area for the customer to sign
	 *
	 *	@param	PDF			$pdf            Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _signature_area(&$pdf, $object, $posy, $outputlangs, &$pagina)
	{
        // phpcs:enable
		global $conf;
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$tab_top = $posy + 4;
		$tab_hl = 4;

		$posx = 120;
		$largcol = ($pagina->page_largeur - $pagina->marge_droite - $posx);
		$useborder=0;
		$index = 0;
		// Total HT
		$pdf->SetFillColor(255, 255, 255);
		print_texto($pdf, $posx, $tab_top + 0, $largcol, $tab_hl, $outputlangs->transnoentities("ProposalCustomerSignature"), 0, 'L', 1, '', '', $default_font_size - 2);	
		print_texto1($pdf, $posx, $tab_top + $tab_hl, $largcol, $tab_hl * 3, '', 1, 'R', 1);	
		if (! empty($conf->global->MAIN_PDF_PROPAL_USE_ELECTRONIC_SIGNING)) {
			$pdf->addEmptySignatureAppearance($posx, $tab_top + $tab_hl, $largcol, $tab_hl*3);
		}

		return ($tab_hl*7);
	}
	
	function print_texto(&$pdf,  $posx, $posy, $width, $height, $texto, $border, $align, $fill, $font, $style, $font_size)
	{
		global $conf;
		$pdf->SetFont($font, $style, $font_size);
		print_texto1($pdf,  $posx, $posy, $width, $height, $texto, $border , $align , $fill);
		
	}
	
	function print_texto1(&$pdf,  $posx, $posy, $width, $height, $texto, $border, $align, $fill)
	{
		global $conf;
		$pdf->SetXY($posx, $posy);
		$pdf->MultiCell($width, $height, $texto, $border, $align, $fill);
	}
?>