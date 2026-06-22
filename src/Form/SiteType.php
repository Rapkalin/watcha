<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Site>
 */
final class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du site',
                'attr' => ['placeholder' => 'Mon site vitrine'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://exemple.com'],
                'help' => "L'URL sera analysée pour détecter le CMS/framework et sa version.",
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Site::class]);
    }
}
