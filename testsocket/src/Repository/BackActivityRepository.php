<?php

namespace App\Repository;

use App\Entity\BackActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BackActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method BackActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method BackActivity[]    findAll()
 * @method BackActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BackActivityRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BackActivity::class);
    }

//    /**
//     * @return BackActivity[] Returns an array of BackActivity objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BackActivity
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
