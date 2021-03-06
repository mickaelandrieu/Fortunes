<?php

namespace Lyrixx\Bundle\FortuneBundle\Controller;

use Lyrixx\Bundle\FortuneBundle\Entity\Fortune;
use Lyrixx\Bundle\FortuneBundle\Form\FortuneType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Template()
 */
class FrontController extends Controller
{
    const CSRF_INTENTION = 'fortune';

    /**
     * @Route("/", name="fortune_list", defaults={"orderBy"=null})
     * @Route("/top", name="fortune_list_top", defaults={"orderBy"="votes_desc"})
     * @Route("/flop", name="fortune_list_flop", defaults={"orderBy"="votes_asc"})
     */
    public function indexAction(Request $request, $orderBy)
    {
        $fortunes = $this->getDoctrine()
            ->getRepository('LyrixxFortuneBundle:Fortune')
            ->findLasts($orderBy)
        ;

        $token = $this->get('form.csrf_provider')->generateCsrfToken(self::CSRF_INTENTION);

        return array('fortunes' => $fortunes, 'token' => $token);
    }

    /**
     * @Route("/new", name="fortune_new")
     */
    public function newAction(Request $request)
    {
        $fortune = new Fortune();
        $form = $this->createForm(new FortuneType(), $fortune);
        if ($request->isMethod('POST') && $form->submit($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($fortune);
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', 'The fortune has been created');

            return $this->redirect($this->generateUrl('fortune_list'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/{id}", name="fortune_show")
     */
    public function showAction(Fortune $fortune)
    {
        $token = $this->get('form.csrf_provider')->generateCsrfToken(self::CSRF_INTENTION);

        return array('fortune' => $fortune, 'token' => $token);
    }

    /**
     * @Route("/vote/{id}/{dir}/{token}",
     *     name="fortune_vote",
     *     requirements={
     *         "id"="\d+",
     *         "dir"="up|down",
     *     }
     * )
     */
    public function voteAction(Request $request, Fortune $fortune, $dir, $token)
    {
        if (!$this->get('form.csrf_provider')->isCsrfTokenValid(self::CSRF_INTENTION, $token)) {
            throw $this->createNotFunction('Invalid CSRF');
        }

        $fortune->vote($dir);
        $this->getDoctrine()->getManager()->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(array('votes' => $fortune->getVotes()));
        }

        return $this->redirect($this->generateUrl('fortune_list'));
    }
}
