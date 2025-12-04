<?php

namespace App\Controller\Admin;

use App\Entity\Emprunt;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EmpruntCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Emprunt::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('utilisateur')
            ->setLabel('User');
        yield AssociationField::new('livre')
            ->setLabel('Book');
        yield DateTimeField::new('dateEmprunt')
            ->setLabel('Borrow Date');
        yield DateTimeField::new('dateRetourPrevue')
            ->setLabel('Due Date');
        yield DateTimeField::new('dateRetourEffective')
            ->setLabel('Return Date')
            ->hideOnForm();
        yield TextField::new('status')
            ->setLabel('Status');
    }
}
