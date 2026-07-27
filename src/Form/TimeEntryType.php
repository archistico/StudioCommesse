<?php

declare(strict_types=1);
namespace App\Form;
use App\Entity\TimeEntry;use Symfony\Component\Form\AbstractType;use Symfony\Component\Form\Extension\Core\Type\CheckboxType;use Symfony\Component\Form\Extension\Core\Type\DateTimeType;use Symfony\Component\Form\Extension\Core\Type\TextareaType;use Symfony\Component\Form\FormBuilderInterface;use Symfony\Component\OptionsResolver\OptionsResolver;
/** @extends AbstractType<TimeEntry> */
final class TimeEntryType extends AbstractType
{public function buildForm(FormBuilderInterface $b,array $o):void{$b->add('startedAt',DateTimeType::class,['label'=>'Inizio','widget'=>'single_text'])->add('endedAt',DateTimeType::class,['label'=>'Fine','widget'=>'single_text'])->add('description',TextareaType::class,['label'=>'Lavoro svolto','required'=>false,'attr'=>['rows'=>3]])->add('billable',CheckboxType::class,['label'=>'Fatturabile','required'=>false]);}public function configureOptions(OptionsResolver $r):void{$r->setDefaults(['data_class'=>TimeEntry::class]);}}
