<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Repository;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailTemplate>
 *
 * @method MailTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailTemplate[]    findAll()
 * @method MailTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailTemplate::class);
    }

    public function save(MailTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MailTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->remove($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
