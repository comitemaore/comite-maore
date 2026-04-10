<?php

namespace App\Form;

use App\Entity\OperationFinance;
use App\Entity\CotisationDue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OperationFinanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('typeOperation', ChoiceType::class, [
                'choices' => [
                    'Paiement cotisation' => 'PAIEMENT',
                    'Autre' => 'AUTRE'
                ]
            ])

            ->add('montant', MoneyType::class)

            ->add('cotisationDue', EntityType::class, [
                'class' => CotisationDue::class,
                'choice_label' => fn($c) => $c->getAnnee().' - '.$c->getMontantDu(),
                'placeholder' => '-- aucune cotisation --',
                'required' => false
            ])

            ->add('note', TextareaType::class, [
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => OperationFinance::class,
        ]);
    }
}