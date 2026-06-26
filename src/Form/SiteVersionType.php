<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Site;
use App\Enum\Technology;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The owner enters the technology and version of their application by hand. Both are needed to run
 * a scan (CVE matching + latest stable lookup); the scan button stays disabled until they are set.
 *
 * @extends AbstractType<Site>
 */
final class SiteVersionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('manualTechnology', EnumType::class, [
                'class' => Technology::class,
                'label' => 'Technologie',
                'required' => false,
                'placeholder' => '— Choisir —',
                'choice_label' => static fn (Technology $t) => $t->label(),
            ])
            ->add('manualVersion', TextType::class, [
                'label' => 'Version',
                'required' => false,
                'attr' => ['placeholder' => 'ex. 7.2.0'],
                'help' => 'Technologie et version sont nécessaires pour lancer un scan.',
                'constraints' => [
                    new Assert\Length(max: 50),
                    new Assert\Regex(
                        pattern: '/^v?\d+(\.\d+){0,3}([.-].+)?$/',
                        message: 'Format de version invalide (ex. 7.2.0).',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Site::class]);
    }
}
